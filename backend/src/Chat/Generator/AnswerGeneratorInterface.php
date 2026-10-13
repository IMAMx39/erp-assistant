<?php

declare(strict_types=1);

namespace App\Chat\Generator;

use App\Chat\Dto\ChatEvent;
use App\Chat\Dto\GenerationResult;
use App\Chat\Exception\GenerationFailedException;
use App\Retrieval\Dto\SearchResult;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Fournisseur de LLM qui rédige la réponse à partir des extraits retrouvés.
 * Le fournisseur actif est choisi par la variable LLM_PROVIDER.
 */
#[AutoconfigureTag]
interface AnswerGeneratorInterface
{
    /**
     * Produit les événements DELTA et CITATION, puis renvoie le bilan de la génération.
     *
     * @param list<SearchResult> $sources non vide
     *
     * @return \Generator<int, ChatEvent, mixed, GenerationResult>
     *
     * @throws GenerationFailedException
     */
    public function generate(string $question, array $sources): \Generator;
}
