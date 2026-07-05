<?php

declare(strict_types=1);

namespace App\Command;

use App\Common\MediaLookup;
use App\Entity\Album;
use Doctrine\ORM\EntityManagerInterface;
use Sulu\Article\Application\Message\ApplyWorkflowTransitionArticleMessage;
use Sulu\Article\Application\Message\CreateArticleMessage;
use Sulu\Article\Application\Message\ModifyArticleMessage;
use Sulu\Article\Domain\Model\ArticleInterface;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Page\Application\Message\ApplyWorkflowTransitionPageMessage;
use Sulu\Page\Application\Message\CreatePageMessage;
use Sulu\Page\Application\Message\ModifyPageMessage;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Reads data/*.jsonl (produced by app:legacy-fixtures-jsonl) and dispatches the
 * real Sulu\Article and Sulu\Page create/modify/workflow messages to populate
 * the database, instead of a bespoke entity per content type. Requires the
 * album fixtures (bin/console doctrine:fixtures:load) to already be loaded,
 * since "albums" blocks link albums by title. Meant to run once against an
 * empty webspace: re-running will fail on duplicate routes.
 */
#[AsCommand('app:import-jsonl', 'Import data/*.jsonl into the database via Sulu content messages')]
final class ImportJsonlCommand
{
    private const array TYPES = ['page', 'article'];
    private const string WEBSPACE_KEY = 'demo';

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly MessageBusInterface $messageBus,
        private readonly EntityManagerInterface $entityManager,
        private readonly MediaLookup $mediaLookup,
        private readonly PageRepositoryInterface $pageRepository,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        foreach (self::TYPES as $type) {
            $path = "{$this->projectDir}/data/{$type}.json";

            if (!is_file($path)) {
                $io->warning(sprintf('%s not found, run app:legacy-fixtures-jsonl first.', $path));
                continue;
            }

            $rows = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            $created = [];

            foreach ($rows as $row) {
                match ($type) {
                    'page' => $this->importPage($row, $created),
                    'article' => $this->importArticle($row, $created),
                };
            }

            $io->writeln(sprintf('Imported %d %s rows', count($rows), $type));
        }

        return Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, string> $created slug => uuid, mutated in place
     */
    private function importPage(array $row, array &$created): void
    {
        $slug = $row['slug'];

        $data = [
            'locale' => $row['locale'],
            'template' => $row['template'],
            'title' => $row['title'],
            'url' => $row['url'],
        ];

        if (isset($row['subtitle'])) {
            $data['subtitle'] = $row['subtitle'];
        }

        if (isset($row['headerImage'])) {
            $data['headerImage'] = $this->mediaReference($row['headerImage']);
        }

        if (isset($row['element'])) {
            $data['element'] = [$row['element']];
        }

        if (isset($row['blocks'])) {
            $data['blocks'] = $this->resolveAlbumBlocks($row['blocks']);
        }

        if (isset($created[$slug])) {
            $message = new ModifyPageMessage(['uuid' => $created[$slug]], $data);
        } else {
            $parentId = null === $row['parentSlug']
                ? $this->pageRepository->getOneBy(['parentId' => null])->getUuid()
                : $created[$row['parentSlug']];

            $message = new CreatePageMessage(self::WEBSPACE_KEY, $parentId, $data);
        }

        /** @var PageInterface $page */
        $page = $this->dispatchAndGetResult($message);
        $created[$slug] = $page->getUuid();

        $this->dispatchAndGetResult(new ApplyWorkflowTransitionPageMessage(
            ['uuid' => $page->getUuid()],
            $row['locale'],
            WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH,
        ));
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, string> $created translationKey => uuid, mutated in place
     */
    private function importArticle(array $row, array &$created): void
    {
        $key = $row['translationKey'];

        $data = [
            'locale' => $row['locale'],
            'template' => $row['structureType'],
            'title' => $row['title'],
            'url' => '/blog/' . $row['slug'],
            'blocks' => $row['blocks'],
        ];

        if (isset($row['headerImage'])) {
            $data['headerImage'] = $this->mediaReference($row['headerImage']);
        }

        if (isset($row['excerptTitle']) || isset($row['excerptDescription'])) {
            $data['excerpt'] = [
                'title' => $row['excerptTitle'] ?? null,
                'description' => $row['excerptDescription'] ?? null,
                'images' => isset($row['excerptImage']) ? ['ids' => [$this->requireMediaId($row['excerptImage'])]] : [],
            ];
        }

        $message = isset($created[$key])
            ? new ModifyArticleMessage(['uuid' => $created[$key]], $data)
            : new CreateArticleMessage($data);

        /** @var ArticleInterface $article */
        $article = $this->dispatchAndGetResult($message);
        $created[$key] = $article->getUuid();

        $this->dispatchAndGetResult(new ApplyWorkflowTransitionArticleMessage(
            ['uuid' => $article->getUuid()],
            $row['locale'],
            WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH,
        ));
    }

    /**
     * @param list<array<string, mixed>> $blocks
     *
     * @return list<array<string, mixed>>
     */
    private function resolveAlbumBlocks(array $blocks): array
    {
        return array_map(function (array $block): array {
            if ('albums' !== ($block['type'] ?? null)) {
                return $block;
            }

            return [
                'type' => 'albums',
                'albums' => array_map($this->requireAlbumId(...), $block['albums']),
            ];
        }, $blocks);
    }

    /**
     * @return array{id: int}
     */
    private function mediaReference(string $filename): array
    {
        return ['id' => $this->requireMediaId($filename)];
    }

    private function requireMediaId(string $filename): int
    {
        $media = $this->mediaLookup->findByFilename($filename);

        if (null === $media) {
            throw new \RuntimeException(sprintf('Media "%s" not found.', $filename));
        }

        return $media->getId();
    }

    private function requireAlbumId(string $title): int
    {
        $album = $this->entityManager->getRepository(Album::class)->findOneBy(['title' => $title]);

        if (!$album instanceof Album) {
            throw new \RuntimeException(sprintf('Album "%s" not found. Have you loaded the album fixtures?', $title));
        }

        return $album->getId();
    }

    private function dispatchAndGetResult(object $message): object
    {
        $envelope = $this->messageBus->dispatch(new Envelope($message, [new EnableFlushStamp()]));

        /** @var HandledStamp[] $stamps */
        $stamps = $envelope->all(HandledStamp::class);

        return $stamps[0]->getResult();
    }
}
