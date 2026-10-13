<?php

declare(strict_types=1);

namespace App\Chat\Generator;

/**
 * Sépare un texte reçu au fil de l'eau en morceaux de texte et marqueurs de citation [n] / [1, 3] / 【n】.
 *
 * Un marqueur peut être coupé entre deux morceaux du flux (« [1, » puis « 3] ») :
 * la fin du tampon qui ressemble à un début de marqueur est retenue jusqu'au morceau suivant.
 * Les espaces juste avant un marqueur sont supprimées (« clôturer [2]. » donne « clôturer. ») ;
 * les espaces en fin de tampon sont donc retenues elles aussi, le temps de voir ce qui suit.
 */
final class CitationMarkerParser
{
    // Crochets ASCII ou « 【3】 », que certains modèles (gpt-oss) utilisent à la place.
    private const MARKER = '/(?:\[|【)(\d{1,2}(?:\s*,\s*\d{1,2})*)(?:\]|】)/u';
    // \h couvre aussi les espaces insécables (U+00A0, U+202F) placées avant un marqueur.
    private const PARTIAL_MARKER = '/\h*((?:\[|【)[\d,\s]{0,12})?$/u';

    private string $buffer = '';

    /**
     * @return list<array{type: 'text', text: string}|array{type: 'cite', sources: list<int>}>
     */
    public function feed(string $text): array
    {
        $this->buffer .= $text;

        return $this->drain(final: false);
    }

    /**
     * @return list<array{type: 'text', text: string}|array{type: 'cite', sources: list<int>}>
     */
    public function flush(): array
    {
        return $this->drain(final: true);
    }

    /**
     * @return list<array{type: 'text', text: string}|array{type: 'cite', sources: list<int>}>
     */
    private function drain(bool $final): array
    {
        $tokens = [];

        while (preg_match(self::MARKER, $this->buffer, $match, \PREG_OFFSET_CAPTURE) === 1) {
            [$marker, $offset] = $match[0];

            $before = (string) preg_replace('/\h+$/u', '', substr($this->buffer, 0, $offset));
            if ($before !== '') {
                $tokens[] = ['type' => 'text', 'text' => $before];
            }

            $tokens[] = [
                'type' => 'cite',
                'sources' => array_map(intval(...), preg_split('/\s*,\s*/', $match[1][0]) ?: []),
            ];
            $this->buffer = substr($this->buffer, $offset + \strlen($marker));
        }

        $pending = '';
        if (!$final && preg_match(self::PARTIAL_MARKER, $this->buffer, $match, \PREG_OFFSET_CAPTURE) === 1) {
            $pending = substr($this->buffer, $match[0][1]);
            $this->buffer = substr($this->buffer, 0, $match[0][1]);
        }

        if ($this->buffer !== '') {
            $tokens[] = ['type' => 'text', 'text' => $this->buffer];
        }
        $this->buffer = $pending;

        return $tokens;
    }
}
