<?php

declare(strict_types=1);

namespace App\Chat\Dto;

/**
 * Événement de génération, transmis tel quel au client en SSE.
 */
final readonly class ChatEvent
{
    public const SOURCES = 'sources';
    public const DELTA = 'delta';
    public const CITATION = 'citation';
    public const DONE = 'done';

    /**
     * @param mixed $data contenu sérialisable en JSON (DTO ou tableau)
     */
    public function __construct(
        public string $type,
        public mixed $data,
    ) {
    }
}
