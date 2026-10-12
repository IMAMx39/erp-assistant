<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Document;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Document>
 */
class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    public function findOneByPath(string $path): ?Document
    {
        return $this->findOneBy(['path' => $path]);
    }

    /**
     * Supprime les documents dont le fichier n'existe plus dans le corpus.
     * Les chunks sont supprimés par le ON DELETE CASCADE de la base.
     *
     * @param non-empty-list<string> $existingPaths
     */
    public function deleteMissing(array $existingPaths): int
    {
        return (int) $this->createQueryBuilder('d')
            ->delete()
            ->where('d.path NOT IN (:paths)')
            ->setParameter('paths', $existingPaths)
            ->getQuery()
            ->execute();
    }
}
