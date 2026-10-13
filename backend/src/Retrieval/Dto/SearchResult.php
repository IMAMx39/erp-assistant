<?php

declare(strict_types=1);

namespace App\Retrieval\Dto;

use App\Entity\Chunk;

final readonly class SearchResult
{
    /**
     * @param ?float $distance distance cosinus à la question (0 = identique, 2 = opposé), null si le
     *                         résultat ne vient pas de la recherche vectorielle
     * @param float $score pertinence selon la méthode qui a classé le résultat (plus haut = mieux)
     */
    public function __construct(
        public int    $chunkId,
        public string $documentPath,
        public string $documentTitle,
        public string $module,
        public string $heading,
        public string $content,
        public ?float $distance,
        public float  $score,
    )
    {
    }

    public static function fromChunk(Chunk $chunk, ?float $distance, float $score): self
    {
        $document = $chunk->getDocument();

        return new self(
            (int)$chunk->getId(),
            $document->getPath(),
            $document->getTitle(),
            $document->getModule(),
            $chunk->getHeading(),
            $chunk->getContent(),
            $distance,
            $score,
        );
    }

    public function withScore(float $score): self
    {
        return new self(
            $this->chunkId,
            $this->documentPath,
            $this->documentTitle,
            $this->module,
            $this->heading,
            $this->content,
            $this->distance,
            $score,
        );
    }
}
