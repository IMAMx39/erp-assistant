<?php

declare(strict_types=1);

namespace App\Agent;

use App\Agent\Tool\AgentToolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Outils de l'agent : définitions transmises au LLM et exécution des appels qu'il demande.
 */
final class ToolRegistry
{
    /** @var array<string, AgentToolInterface> */
    private array $tools = [];

    /**
     * @param iterable<AgentToolInterface> $tools
     */
    public function __construct(
        #[AutowireIterator(AgentToolInterface::class)]
        iterable $tools,
        private readonly LoggerInterface $logger,
    ) {
        foreach ($tools as $tool) {
            $this->tools[$tool->name()] = $tool;
        }
        ksort($this->tools);
    }

    /**
     * @return list<AgentToolInterface>
     */
    public function all(): array
    {
        return array_values($this->tools);
    }

    /**
     * Exécute un appel d'outil. Ne lève jamais d'exception : une erreur est renvoyée au LLM
     * sous forme de résultat, pour qu'il puisse corriger son appel ou répondre sans.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    public function call(string $name, array $arguments): array
    {
        $tool = $this->tools[$name] ?? null;
        if ($tool === null) {
            return ['erreur' => sprintf('Outil inconnu « %s ».', $name)];
        }

        try {
            return $tool->execute($arguments);
        } catch (\Throwable $e) {
            $this->logger->error('Échec d\'un outil de l\'agent', ['outil' => $name, 'arguments' => $arguments, 'exception' => $e]);

            return ['erreur' => 'L\'outil a rencontré une erreur interne.'];
        }
    }
}
