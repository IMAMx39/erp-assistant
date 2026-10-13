<?php

declare(strict_types=1);

namespace App\Chat\Exception;

/**
 * Échec du fournisseur de LLM, avec un message affichable à l'utilisateur.
 */
final class GenerationFailedException extends \RuntimeException
{
    public function __construct(
        string              $message,
        public readonly int $statusCode = 502,
        ?\Throwable         $previous = null,
    )
    {
        parent::__construct($message, 0, $previous);
    }
}
