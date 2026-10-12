<?php

declare(strict_types=1);

namespace App\Chat\Dto;

final readonly class Citation
{
    /**
     * @param int $source numéro de la source (1 = premier extrait envoyé au modèle)
     * @param int $block  bloc de texte de la réponse auquel la citation se rattache
     */
    public function __construct(
        public int $source,
        public int $chunkId,
        public string $documentPath,
        public string $citedText,
        public int $block,
    ) {
    }
}
