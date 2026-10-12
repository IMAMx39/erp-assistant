<?php

declare(strict_types=1);

namespace App\Retrieval\Dto;

final readonly class SearchResult
{
    /**
     * @param float $distance distance cosinus (0 = identique, 2 = opposé)
     */
    public function __construct(
        public int $chunkId,
        public string $documentPath,
        public string $documentTitle,
        public string $module,
        public string $heading,
        public string $content,
        public float $distance,
    ) {
    }
}
