<?php

declare(strict_types=1);

namespace App\Ingestion\Message;

final readonly class IngestDocument
{
    public function __construct(
        public string $relativePath,
        public bool $force = false,
    ) {
    }
}
