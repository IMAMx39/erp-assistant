<?php

declare(strict_types=1);

namespace App\Retrieval\Controller;

use App\Retrieval\VectorRetriever;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

final class SearchController extends AbstractController
{
    private const MAX_LIMIT = 20;

    #[Route('/api/search', name: 'api_search', methods: ['GET'])]
    public function __invoke(
        VectorRetriever $retriever,
        #[MapQueryParameter] string $q = '',
        #[MapQueryParameter] int $limit = 5,
    ): JsonResponse {
        $query = trim($q);

        if ($query === '') {
            return $this->json(['error' => 'Le paramètre « q » est obligatoire.'], 400);
        }

        return $this->json([
            'query' => $query,
            'results' => $retriever->search($query, max(1, min($limit, self::MAX_LIMIT))),
        ]);
    }
}
