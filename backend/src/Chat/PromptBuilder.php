<?php

declare(strict_types=1);

namespace App\Chat;

use App\Retrieval\Dto\SearchResult;

/**
 * Construit le prompt système et le message utilisateur envoyés à Claude.
 *
 * Chaque extrait est un bloc « document » avec citations activées : l'API renvoie
 * alors le passage exact qui justifie chaque partie de la réponse.
 */
final class PromptBuilder
{
    public const NOT_FOUND_ANSWER = 'Je ne trouve pas cette information dans la documentation Gestix.';

    public function system(): string
    {
        $notFound = self::NOT_FOUND_ANSWER;

        return <<<PROMPT
            Tu es l'assistant de la documentation de Gestix, un ERP de gestion commerciale et logistique.

            Réponds en français à la question de l'utilisateur en t'appuyant uniquement sur les extraits de documentation fournis, et cite les passages qui justifient ta réponse.
            N'invente aucun menu, règle, seuil, rôle ni code qui ne figure pas dans les extraits.
            Si les extraits ne permettent pas de répondre, réponds exactement « {$notFound} » sans rien ajouter.
            Sois concis : quelques phrases ou une courte liste.
            PROMPT;
    }

    /**
     * @param list<SearchResult> $sources
     *
     * @return list<array<string, mixed>>
     */
    public function messages(string $question, array $sources): array
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
}
