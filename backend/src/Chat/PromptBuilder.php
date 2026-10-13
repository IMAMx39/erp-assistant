<?php

declare(strict_types=1);

namespace App\Chat;

use App\Retrieval\Dto\SearchResult;

/**
 * Construit le prompt système et le message utilisateur envoyés au LLM.
 *
 * Deux façons de citer :
 *  - blocs « document » avec citations natives (Claude) : l'API renvoie le passage exact cité ;
 *  - extraits numérotés et marqueurs [n] dans le texte (fournisseurs sans citations natives).
 */
final class PromptBuilder
{
    public const NOT_FOUND_ANSWER = 'Je ne trouve pas cette information dans la documentation Gestix.';

    public function system(bool $inlineMarkers = false): string
    {
        $notFound = self::NOT_FOUND_ANSWER;
        $citeRule = $inlineMarkers
            ? 'Après chaque phrase qui s\'appuie sur un extrait, indique son numéro entre crochets, par exemple [2] ou [1, 3].'
            : 'Cite les passages qui justifient ta réponse.';

        return <<<PROMPT
            Tu es l'assistant de la documentation de Gestix, un ERP de gestion commerciale et logistique.

            Réponds en français à la question de l'utilisateur en t'appuyant uniquement sur les extraits de documentation fournis.
            {$citeRule}
            N'invente aucun menu, règle, seuil, rôle ni code qui ne figure pas dans les extraits.
            Si les extraits ne permettent pas de répondre, réponds exactement « {$notFound} » sans rien ajouter.
            Sois concis : quelques phrases ou une courte liste.
            PROMPT;
    }

    /**
     * Message avec un bloc « document » par extrait, citations natives activées.
     *
     * @param list<SearchResult> $sources
     *
     * @return list<array<string, mixed>>
     */
    public function documentMessages(string $question, array $sources): array
    {
        $content = array_map(static fn (SearchResult $source): array => [
            'type' => 'document',
            'source' => ['type' => 'text', 'mediaType' => 'text/plain', 'data' => $source->content],
            'title' => $source->heading,
            'context' => sprintf('Fichier : %s (module %s)', $source->documentPath, $source->module),
            'citations' => ['enabled' => true],
        ], $sources);

        $content[] = ['type' => 'text', 'text' => $question];

        return [['role' => 'user', 'content' => $content]];
    }

    /**
     * Message texte avec les extraits numérotés à partir de 1, à citer par marqueurs [n].
     *
     * @param list<SearchResult> $sources
     */
    public function numberedSourcesMessage(string $question, array $sources): string
    {
        $extracts = [];
        foreach ($sources as $i => $source) {
            $extracts[] = sprintf(
                "[%d] %s (fichier %s)\n%s",
                $i + 1,
                $source->heading,
                $source->documentPath,
                $source->content,
            );
        }

        return "Extraits de documentation :\n\n".implode("\n\n", $extracts)."\n\nQuestion : ".$question;
    }
}
