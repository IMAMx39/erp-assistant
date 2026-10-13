<?php

declare(strict_types=1);

namespace App\Chat\Dto;

use App\Retrieval\RetrievalMode;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ChatRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'La question est obligatoire.')]
        #[Assert\Length(max: 1000, maxMessage: 'La question ne doit pas dépasser {{ limit }} caractères.')]
        public string $question = '',
        /** Mode de recherche, pour comparer les modes en évaluation ; par défaut RETRIEVAL_MODE. */
        #[Assert\Choice(callback: [RetrievalMode::class, 'values'], message: 'Mode de recherche inconnu.')]
        public ?string $mode = null,
    ) {
    }

    public function retrievalMode(): ?RetrievalMode
    {
        return $this->mode === null ? null : RetrievalMode::from($this->mode);
    }
}
