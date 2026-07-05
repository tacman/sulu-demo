<?php

declare(strict_types=1);

namespace App\Common;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Sulu\Bundle\MediaBundle\Entity\Media;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;

final class MediaLookup
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function findByFilename(?string $filename): ?MediaInterface
    {
        if (null === $filename) {
            return null;
        }

        try {
            return $this->entityManager->createQueryBuilder()
                ->select('media')
                ->from(Media::class, 'media')
                ->innerJoin('media.files', 'file')
                ->innerJoin('file.fileVersions', 'fileVersion')
                ->where('fileVersion.name = :name')
                ->setParameter('name', $filename)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        } catch (NonUniqueResultException $e) {
            throw new \RuntimeException(\sprintf('Too many images with the name "%s" found.', $filename), 0, $e);
        }
    }
}
