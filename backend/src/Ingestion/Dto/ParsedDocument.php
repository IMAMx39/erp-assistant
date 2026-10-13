<?php

declare(strict_types=1);

namespace App\Ingestion\Dto;

final readonly class ParsedDocument
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public string $title,
        public string $module,
        public array  $tags,
        public string $body,
    )
    {
    }
}
