<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Agent\Tool\AgentToolInterface;
use App\Agent\ToolRegistry;
use Mcp\Capability\Registry\Loader\ExplicitElementLoader;
use Mcp\Capability\Registry\Loader\LoaderInterface;
use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Tool;
use Mcp\Schema\ToolAnnotations;

/**
 * Enregistre dans le serveur MCP les outils de l'agent (définis une seule fois, dans src/Agent/Tool)
 * et la recherche documentaire. Tous sont en lecture seule.
 */
final class ErpToolLoader implements LoaderInterface
{
    public function __construct(
        private readonly ToolRegistry $tools,
        private readonly DocumentationSearchTool $documentationSearch,
    ) {
    }

    public function load(RegistryInterface $registry): void
    {
        $entries = array_map(static fn (AgentToolInterface $tool): array => [
            'definition' => new Tool(
                name: $tool->name(),
                title: null,
                inputSchema: $tool->parameters(),
                description: $tool->description(),
                annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false),
            ),
            'handler' => new AgentToolHandler($tool),
        ], [...$this->tools->all(), $this->documentationSearch]);

        (new ExplicitElementLoader(tools: $entries))->load($registry);
    }
}
