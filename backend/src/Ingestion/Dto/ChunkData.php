<?php

declare(strict_types=1);

namespace App\Ingestion\Dto;

final readonly class ChunkData
{
    public function __construct(
        public int    $position,
        public string $heading,
        public string $content,
    )
    {
    }
}
