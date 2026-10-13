<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Agent\Tool\AgentToolInterface;
use App\Observability\LangfuseTracer;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\ClientGateway;
use Mcp\Server\Handler\ToolHandlerInterface;

/**
 * Expose un outil de l'agent comme outil MCP : même code, mêmes résultats, une trace Langfuse par appel.
 */
final class AgentToolHandler implements ToolHandlerInterface
{
    public function __construct(
        private readonly AgentToolInterface $tool,
        private readonly LangfuseTracer $tracer,
    ) {
    }

    public function execute(array $arguments, ClientGateway $gateway): mixed
    {
        // Une trace par appel, envoyée aussitôt : le serveur MCP (stdio) est un processus qui reste ouvert.
        $this->tracer->startTrace('mcp '.$this->tool->name(), $arguments, [], ['mcp']);
        try {
            $result = $this->tracer->span('outil '.$this->tool->name(), $arguments, fn (): array => $this->tool->execute($arguments));
            $this->tracer->endTrace($result);
        } finally {
            $this->tracer->flush();
        }

        // Résultat « erreur » de l'outil (code inconnu…) : signalé comme tel au client MCP.
        if (isset($result['erreur']) && \is_string($result['erreur'])) {
            return CallToolResult::error([new TextContent(json_encode($result, \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR))]);
        }

        return $result;
    }
}
