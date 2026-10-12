<?php

declare(strict_types=1);

namespace App\Chat\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ChatRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'La question est obligatoire.')]
        #[Assert\Length(max: 1000, maxMessage: 'La question ne doit pas dépasser {{ limit }} caractères.')]
        public string $question = '',
    ) {
    }
}
