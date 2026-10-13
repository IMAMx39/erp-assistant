<?php

declare(strict_types=1);

namespace App\Chat\Dto;

final readonly class GenerationResult
{
    /**
     * @param ?string $stopReason raison de fin renvoyée par le fournisseur (« refusal » = blocage de sécurité)
     */
    public function __construct(
        public string  $model,
        public ?string $stopReason,
        public int     $inputTokens,
        public int     $outputTokens,
    )
    {
    }
}
