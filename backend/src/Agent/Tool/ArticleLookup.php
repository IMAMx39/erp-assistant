<?php

declare(strict_types=1);

namespace App\Agent\Tool;

use App\Erp\Entity\Article;
use App\Erp\Repository\ArticleRepository;

/**
 * Recherche d'un article par son code, partagée par les outils.
 */
final readonly class ArticleLookup
{
    public function __construct(
        private ArticleRepository $articleRepository,
    )
    {
    }

    public function find(mixed $code): ?Article
    {
        if (!\is_string($code) || trim($code) === '') {
            return null;
        }

        return $this->articleRepository->findOneBy(['code' => strtoupper(trim($code))]);
    }
}
