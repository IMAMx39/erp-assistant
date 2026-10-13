<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Agent\Tool\AgentToolInterface;
use App\Retrieval\DocumentRetriever;
use App\Retrieval\Dto\SearchResult;
use App\Retrieval\RetrievalMode;

/**
 * Recherche dans la documentation Gestix, pour les clients MCP (Claude Desktop…).
 *
 * Réservé au MCP : l'agent interne reçoit déjà les extraits pertinents dans son contexte.
 * Mode hybride sans reclassement : le client est lui-même un LLM capable de trier les extraits,
 * inutile de consommer le quota Groq.
 */
final class DocumentationSearchTool implements AgentToolInterface
{
    private const LIMIT = 5;

    public function __construct(
        private readonly DocumentRetriever $retriever,
    ) {
    }

    public function name(): string
    {
        return 'search_documentation';
    }

    public function description(): string
    {
        return 'Recherche dans la documentation utilisateur de Gestix (ERP fictif de Distrilog) : règles métier, menus, '
            .'statuts, rôles, seuils (inventaires, commandes, réceptions, facturation, relances, TVA…). Renvoie les 5 extraits '
            .'les plus pertinents avec leur fichier source. À utiliser pour expliquer une règle ou une procédure ; '
            .'pour une donnée précise (stock, commande, client), utiliser les autres outils.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Question ou mots-clés, en français'],
            ],
            'required' => ['query'],
        ];
    }

    public function execute(array $arguments): array
    {
        $query = \is_string($arguments['query'] ?? null) ? trim($arguments['query']) : '';
        if ($query === '') {
            return ['erreur' => 'Indiquez une question ou des mots-clés.'];
        }

        return [
            'question' => $query,
            'extraits' => array_map(static fn (SearchResult $result): array => [
                'fichier' => $result->documentPath,
                'section' => $result->heading,
                'contenu' => $result->content,
            ], $this->retriever->search($query, self::LIMIT, RetrievalMode::Hybrid)),
        ];
    }
}
