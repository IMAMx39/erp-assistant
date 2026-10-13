<?php

declare(strict_types=1);

namespace App\Retrieval\Controller;

use App\Retrieval\DocumentRetriever;
use App\Retrieval\RetrievalMode;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

final class SearchController extends AbstractController
{
    private const MAX_LIMIT = 20;

    #[Route('/api/search', name: 'api_search', methods: ['GET'])]
    public function __invoke(
        DocumentRetriever            $retriever,
        #[MapQueryParameter] string  $q = '',
        #[MapQueryParameter] int     $limit = 5,
        #[MapQueryParameter] ?string $mode = null,
    ): JsonResponse
    {
        $query = trim($q);

        if ($query === '') {
            return $this->json(['error' => 'Le paramètre « q » est obligatoire.'], 400);
        }

        $retrievalMode = $mode === null ? $retriever->defaultMode() : RetrievalMode::tryFrom($mode);
        if ($retrievalMode === null) {
            return $this->json(['error' => sprintf('Mode inconnu (valeurs possibles : %s).', implode(', ', RetrievalMode::values()))], 400);
        }

        return $this->json([
            'query' => $query,
            'mode' => $retrievalMode->value,
            'results' => $retriever->search($query, max(1, min($limit, self::MAX_LIMIT)), $retrievalMode),
        ]);
    }
}
