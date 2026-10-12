<?php

declare(strict_types=1);

namespace App\Ingestion\Parser;

use App\Ingestion\Dto\ParsedDocument;
use Symfony\Component\Yaml\Yaml;

final class MarkdownParser
{
    private const FRONT_MATTER_PATTERN = '/\A---\R(.*?)\R---\R?/s';

    public function parse(string $raw, string $fallbackTitle): ParsedDocument
    {
        $raw = preg_replace('/\A\xEF\xBB\xBF/', '', $raw) ?? $raw;
        $meta = [];
        $body = $raw;

        if (preg_match(self::FRONT_MATTER_PATTERN, $raw, $matches) === 1) {
            $parsed = Yaml::parse($matches[1]);
            $meta = \is_array($parsed) ? $parsed : [];
            $body = substr($raw, \strlen($matches[0]));
        }

        return new ParsedDocument(
            title: (string) ($meta['title'] ?? $fallbackTitle),
            module: (string) ($meta['module'] ?? 'general'),
            tags: array_values(array_map('strval', (array) ($meta['tags'] ?? []))),
            body: trim($body),
        );
    }
}
