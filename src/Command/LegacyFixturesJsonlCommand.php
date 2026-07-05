<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Exports the versioned legacy content snapshot (src/DataFixtures/Legacy/*.json,
 * hand-ported from the pre-Sulu-3 PHPCR document fixture arrays) into the
 * working data/ directory that app:import-jsonl reads from.
 *
 * This demo dataset is a few dozen rows, so plain json_decode()/json_encode()
 * is enough — no streaming library needed. For much larger datasets, reach for
 * something like halaxa/json-machine (constant-memory JSON parsing) or a
 * line-delimited JSONL format with a streaming reader/writer.
 */
#[AsCommand('app:legacy-fixtures-jsonl', 'Export the legacy content snapshot into data/*.json')]
final class LegacyFixturesJsonlCommand
{
    private const array TYPES = ['page', 'article'];

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $targetDir = $this->projectDir . '/data';

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new \RuntimeException(sprintf('Unable to create directory "%s".', $targetDir));
        }

        foreach (self::TYPES as $type) {
            $sourcePath = $this->projectDir . "/src/DataFixtures/Legacy/{$type}.json";
            $targetPath = "{$targetDir}/{$type}.json";

            $rows = json_decode(file_get_contents($sourcePath), true, flags: JSON_THROW_ON_ERROR);

            file_put_contents(
                $targetPath,
                json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n",
            );

            $io->writeln(sprintf('Wrote %d rows to %s', count($rows), $targetPath));
        }

        return Command::SUCCESS;
    }
}
