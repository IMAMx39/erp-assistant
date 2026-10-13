<?php

declare(strict_types=1);

namespace App\Agent\Tool;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Outil de consultation de l'ERP fictif, appelable par le LLM. Lecture seule.
 */
#[AutoconfigureTag]
interface AgentToolInterface
{
    /** Nom technique transmis au LLM (snake_case). */
    public function name(): string;

    /** Quand et pourquoi utiliser l'outil : c'est ce qui guide le choix du LLM. */
    public function description(): string;

    /**
     * Schéma JSON des paramètres.
     *
     * @return array<string, mixed>
     */
    public function parameters(): array;

    /**
     * @param array<string, mixed> $arguments arguments fournis par le LLM (non fiables : à valider)
     *
     * @return array<string, mixed> résultat sérialisable en JSON, renvoyé au LLM
     */
    public function execute(array $arguments): array;
}
