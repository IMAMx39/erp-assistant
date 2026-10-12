<?php

declare(strict_types=1);

namespace App\Ingestion\Chunker;

use App\Ingestion\Dto\ChunkData;
use App\Ingestion\Dto\ParsedDocument;

/**
 * Découpe un document par section de niveau 2 (##).
 * Une section trop longue est redécoupée par paragraphes.
 */
final class MarkdownChunker
{
    public function __construct(
        private readonly int $maxChars = 1500,
    ) {
    }

    /**
     * @return list<ChunkData>
     */
    public function chunk(ParsedDocument $document): array
    {
        $chunks = [];

        foreach ($this->splitSections($document->body) as [$heading, $content]) {
            $headingPath = $heading === null ? $document->title : $document->title.' > '.$heading;

            foreach ($this->splitBySize($content) as $part) {
                $chunks[] = new ChunkData(\count($chunks), $headingPath, $part);
            }
        }

        return $chunks;
    }

    /**
     * @return list<array{0: ?string, 1: string}>
     */
    private function splitSections(string $body): array
    {
        $sections = [];
        $heading = null;
        $buffer = [];
        $inCodeFence = false;

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            if (str_starts_with(ltrim($line), '```')) {
                $inCodeFence = !$inCodeFence;
            }

            if (!$inCodeFence) {
                // Le titre H1 est déjà porté par le titre du document.
                if (preg_match('/^#\s+/', $line) === 1) {
                    continue;
                }

                if (preg_match('/^##\s+(.+)$/', $line, $matches) === 1) {
                    $sections[] = [$heading, trim(implode("\n", $buffer))];
                    $heading = trim($matches[1]);
                    $buffer = [];
                    continue;
                }
            }

            $buffer[] = $line;
        }

        $sections[] = [$heading, trim(implode("\n", $buffer))];

        return array_values(array_filter(
            $sections,
            static fn (array $section): bool => $section[1] !== '',
        ));
    }

    /**
     * @return list<string>
     */
    private function splitBySize(string $content): array
    {
        if (mb_strlen($content) <= $this->maxChars) {
            return [$content];
        }

        $parts = [];
        $current = '';

        foreach (preg_split('/\n{2,}/', $content) ?: [] as $paragraph) {
            if ($current !== '' && mb_strlen($current) + mb_strlen($paragraph) + 2 > $this->maxChars) {
                $parts[] = $current;
                $current = '';
            }

            $current = $current === '' ? $paragraph : $current."\n\n".$paragraph;
        }

        if ($current !== '') {
            $parts[] = $current;
        }

        return $parts;
    }
}
