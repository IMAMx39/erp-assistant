<?php

declare(strict_types=1);

namespace App\Chat\Dto;

use App\Retrieval\Dto\SearchResult;

final readonly class ChatAnswer
{
    /**
     * @param list<Citation>     $citations
     * @param list<array{name: string, arguments: array<string, mixed>, error: ?string}> $tools outils de l'agent appelés
     * @param list<SearchResult> $sources
     */
    public function __construct(
        public string $question,
        public string $answer,
        public bool $answered,
        public array $citations,
        public array $tools,
        public array $sources,
        public ?string $model,
        public ?string $stopReason,
        public int $inputTokens,
        public int $outputTokens,
    ) {
    }
}
