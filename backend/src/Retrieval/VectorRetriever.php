<?php

declare(strict_types=1);

namespace App\Retrieval;

use App\Embedding\EmbedderInterface;
use App\Entity\Chunk;
use App\Repository\ChunkRepository;
use App\Retrieval\Dto\SearchResult;

/**
 * Recherche sémantique : plus proches voisins de la question dans l'index vectoriel.
 */
final readonly class VectorRetriever
{
    public function __construct(
        private EmbedderInterface $embedder,
        private ChunkRepository   $chunkRepository,
    )
    {
    }

    /**
     * @return list<SearchResult> triés du plus proche au plus éloigné
     */
    public function search(string $query, int $limit = 5): array
    {
        $rows = $this->chunkRepository->findNearestWithDistance(
            'embedding',
            $this->embedder->embedQuery($query),
            $limit,
        );

        return array_map(static function (array $row): SearchResult {
            /** @var Chunk $chunk */
            $chunk = $row[0];
            $distance = (float)$row['distance'];

            return SearchResult::fromChunk($chunk, $distance, 1 - $distance);
        }, $rows);
    }
}
