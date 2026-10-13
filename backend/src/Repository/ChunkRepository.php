<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Chunk;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use INM39\MariadbVectorBundle\Repository\VectorSearchTrait;

/**
 * @extends ServiceEntityRepository<Chunk>
 */
class ChunkRepository extends ServiceEntityRepository
{
    use VectorSearchTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Chunk::class);
    }

    /**
     * Charge les chunks et leur document en une requête, dans l'ordre des identifiants donnés.
     *
     * @param list<int> $ids
     *
     * @return list<Chunk>
     */
    public function findWithDocuments(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var list<Chunk> $chunks */
        $chunks = $this->createQueryBuilder('c')
            ->addSelect('d')
            ->join('c.document', 'd')
            ->where('c.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $position = array_flip($ids);
        usort($chunks, static fn (Chunk $a, Chunk $b): int => $position[$a->getId()] <=> $position[$b->getId()]);

        return $chunks;
    }
}
