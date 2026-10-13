<?php

declare(strict_types=1);

namespace App\Erp\Repository;

use App\Erp\Entity\Article;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Article>
 */
class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    /**
     * Recherche par mots du libellé ou de la famille (tous les mots doivent figurer).
     *
     * @return list<Article>
     */
    public function search(string $query, ?string $family = null, int $limit = 10): array
    {
        $qb = $this->createQueryBuilder('a')->orderBy('a.code')->setMaxResults($limit);

        $words = preg_split('/\s+/', trim($query), flags: \PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($words as $i => $word) {
            $qb->andWhere("a.label LIKE :w{$i} OR a.family LIKE :w{$i} OR a.code LIKE :w{$i}")
                ->setParameter("w{$i}", '%'.$word.'%');
        }

        if ($family !== null && $family !== '') {
            $qb->andWhere('a.family LIKE :family')->setParameter('family', '%'.$family.'%');
        }

        /** @var list<Article> */
        return $qb->getQuery()->getResult();
    }
}
