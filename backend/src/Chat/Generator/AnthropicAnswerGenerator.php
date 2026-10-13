<?php

declare(strict_types=1);

namespace App\Chat\Generator;

use Anthropic\Client;
use Anthropic\Core\Exceptions\AnthropicException;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\BadRequestException;
use Anthropic\Core\Exceptions\RateLimitException;
use Anthropic\Messages\CitationCharLocation;
use Anthropic\Messages\CitationsDelta;
use Anthropic\Messages\RawContentBlockDeltaEvent;
use Anthropic\Messages\RawMessageDeltaEvent;
use Anthropic\Messages\RawMessageStartEvent;
use Anthropic\Messages\TextDelta;
use App\Chat\Dto\ChatEvent;
use App\Chat\Dto\Citation;
use App\Chat\Dto\GenerationResult;
use App\Chat\Exception\GenerationFailedException;
use App\Chat\PromptBuilder;
use App\Retrieval\Dto\SearchResult;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Claude, avec les citations natives de l'API : chaque extrait est un bloc « document »
 * et l'API renvoie le passage exact qui justifie chaque partie de la réponse.
 */
#[AsTaggedItem('anthropic')]
final class AnthropicAnswerGenerator implements AnswerGeneratorInterface
{
    private const MAX_TOKENS = 16000;

    public function __construct(
        private readonly Client $anthropic,
        private readonly PromptBuilder $promptBuilder,
        #[Autowire('%env(ANTHROPIC_MODEL)%')]
        private readonly string $model,
    ) {
    }

    public function generate(string $question, array $sources): \Generator
    {
        try {
            $stream = $this->anthropic->messages->createStream(
                maxTokens: self::MAX_TOKENS,
                messages: $this->promptBuilder->documentMessages($question, $sources),
                model: $this->model,
                // Questions-réponses sur des extraits courts : peu de réflexion nécessaire.
                outputConfig: ['effort' => 'low'],
                system: $this->promptBuilder->system(),
            );

            $stopReason = null;
            $inputTokens = 0;
            $outputTokens = 0;

            foreach ($stream as $event) {
                if ($event instanceof RawMessageStartEvent) {
                    $inputTokens = (int) $event->message->usage->inputTokens;
                } elseif ($event instanceof RawContentBlockDeltaEvent && $event->delta instanceof TextDelta) {
                    yield new ChatEvent(ChatEvent::DELTA, ['block' => $event->index, 'text' => $event->delta->text]);
                } elseif ($event instanceof RawContentBlockDeltaEvent && $event->delta instanceof CitationsDelta) {
                    $citation = $this->citation($event->delta, $event->index, $sources);
                    if ($citation !== null) {
                        yield new ChatEvent(ChatEvent::CITATION, $citation);
                    }
                } elseif ($event instanceof RawMessageDeltaEvent) {
                    $stopReason = $event->delta->stopReason;
                    $outputTokens = $event->usage->outputTokens;
                }
            }
        } catch (AnthropicException $e) {
            throw match (true) {
                $e instanceof AuthenticationException => new GenerationFailedException('Clé API Anthropic absente ou invalide (ANTHROPIC_API_KEY).', 500, $e),
                $e instanceof BadRequestException && str_contains($e->getMessage(), 'credit balance') => new GenerationFailedException('Crédit Anthropic épuisé : rechargez le compte ou passez LLM_PROVIDER=mistral.', 402, $e),
                $e instanceof RateLimitException => new GenerationFailedException('Trop de demandes en cours, réessayez dans quelques instants.', 503, $e),
                $e instanceof APIConnectionException => new GenerationFailedException('Le service de génération est injoignable.', 503, $e),
                default => new GenerationFailedException('Le service de génération a renvoyé une erreur.', 502, $e),
            };
        }

        return new GenerationResult($this->model, $stopReason, $inputTokens, $outputTokens);
    }

    /**
     * @param list<SearchResult> $sources
     */
    private function citation(CitationsDelta $delta, int $block, array $sources): ?Citation
    {
        $location = $delta->citation;

        // Les documents texte produisent des citations par position de caractères.
        if (!$location instanceof CitationCharLocation) {
            return null;
        }

        $source = $sources[$location->documentIndex] ?? null;
        if ($source === null) {
            return null;
        }

        return new Citation(
            $location->documentIndex + 1,
            $source->chunkId,
            $source->documentPath,
            $location->citedText,
            $block,
        );
    }
}
