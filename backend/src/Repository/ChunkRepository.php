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
}
