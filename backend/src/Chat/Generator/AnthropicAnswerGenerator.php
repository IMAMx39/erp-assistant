<?php

declare(strict_types=1);

namespace App\Chat\Generator;

use Anthropic\Client;
use Anthropic\Core\Exceptions\AnthropicException;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\BadRequestException;
use Anthropic\Core\Exceptions\RateLimitException;
use Anthropic\Lib\Streaming\MessageAccumulator;
use Anthropic\Messages\CitationCharLocation;
use Anthropic\Messages\CitationsDelta;
use Anthropic\Messages\RawContentBlockDeltaEvent;
use Anthropic\Messages\RawMessageDeltaEvent;
use Anthropic\Messages\RawMessageStartEvent;
use Anthropic\Messages\TextDelta;
use Anthropic\Messages\ToolUseBlock;
use App\Agent\Tool\AgentToolInterface;
use App\Agent\ToolRegistry;
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
 *
 * Agent : outils natifs de l'API ; à chaque tour, le message de l'assistant (réflexion comprise)
 * est reconstitué par MessageAccumulator et renvoyé tel quel avec les résultats des outils.
 */
#[AsTaggedItem('anthropic')]
final class AnthropicAnswerGenerator implements AnswerGeneratorInterface
{
    private const MAX_TOKENS = 16000;
    /** Tours avec outils autorisés avant d'exiger une réponse. */
    private const MAX_TOOL_ROUNDS = 5;

    public function __construct(
        private readonly Client        $anthropic,
        private readonly PromptBuilder $promptBuilder,
        private readonly ToolRegistry  $tools,
        #[Autowire('%env(ANTHROPIC_MODEL)%')]
        private readonly string        $model,
    )
    {
    }

    public function generate(string $question, array $sources): \Generator
    {
        $tools = array_map(static fn(AgentToolInterface $tool): array => [
            'name' => $tool->name(),
            'description' => $tool->description(),
            'inputSchema' => $tool->parameters(),
        ], $this->tools->all());

        $messages = $this->promptBuilder->documentMessages($question, $sources);
        $stopReason = null;
        $inputTokens = 0;
        $outputTokens = 0;
        // Les index de blocs repartent de 0 à chaque tour : décalage pour que l'interface ne les mélange pas.
        $blockOffset = 0;

        try {
            for ($round = 1; ; ++$round) {
                $accumulator = MessageAccumulator::forMessages();
                $stream = $this->anthropic->messages->createStream(
                    maxTokens: self::MAX_TOKENS,
                    messages: $messages,
                    model: $this->model,
                    // Questions-réponses sur des extraits courts : peu de réflexion nécessaire.
                    outputConfig: ['effort' => 'low'],
                    system: $this->promptBuilder->system(tools: $tools !== []),
                    // Dernier tour autorisé : outils interdits, Claude doit répondre.
                    toolChoice: $tools !== [] && $round > self::MAX_TOOL_ROUNDS ? ['type' => 'none'] : null,
                    tools: $tools === [] ? null : $tools,
                );

                $roundOutputTokens = 0;
                foreach ($stream as $event) {
                    $accumulator->accumulate($event);

                    if ($event instanceof RawMessageStartEvent) {
                        $inputTokens += (int)$event->message->usage->inputTokens;
                    } elseif ($event instanceof RawContentBlockDeltaEvent && $event->delta instanceof TextDelta) {
                        yield new ChatEvent(ChatEvent::DELTA, ['block' => $blockOffset + $event->index, 'text' => $event->delta->text]);
                    } elseif ($event instanceof RawContentBlockDeltaEvent && $event->delta instanceof CitationsDelta) {
                        $citation = $this->citation($event->delta, $blockOffset + $event->index, $sources);
                        if ($citation !== null) {
                            yield new ChatEvent(ChatEvent::CITATION, $citation);
                        }
                    } elseif ($event instanceof RawMessageDeltaEvent) {
                        $stopReason = $event->delta->stopReason;
                        // Total cumulé du tour.
                        $roundOutputTokens = $event->usage->outputTokens;
                    }
                }
                $outputTokens += $roundOutputTokens;

                $message = $accumulator->message();
                $blockOffset += \count($message->content);
                $toolUses = array_values(array_filter($message->content, static fn(object $block): bool => $block instanceof ToolUseBlock));

                if ($stopReason !== 'tool_use' || $toolUses === []) {
                    break;
                }

                // Message de l'assistant renvoyé tel quel (réflexion comprise), puis les résultats des outils.
                $messages[] = ['role' => 'assistant', 'content' => array_map(
                    static fn(object $block): mixed => $block instanceof \JsonSerializable ? $block->jsonSerialize() : $block,
                    $message->content,
                )];

                $results = [];
                foreach ($toolUses as $use) {
                    $result = $this->tools->call($use->name, $use->input);
                    $error = isset($result['erreur']) && \is_string($result['erreur']) ? $result['erreur'] : null;

                    yield new ChatEvent(ChatEvent::TOOL, ['name' => $use->name, 'arguments' => $use->input, 'error' => $error]);

                    $results[] = [
                        'type' => 'tool_result',
                        'toolUseID' => $use->id,
                        'content' => json_encode($result, \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR),
                        'isError' => $error !== null,
                    ];
                }
                $messages[] = ['role' => 'user', 'content' => $results];
            }
        } catch (AnthropicException $e) {
            throw match (true) {
                $e instanceof AuthenticationException => new GenerationFailedException('Clé API Anthropic absente ou invalide (ANTHROPIC_API_KEY).', 500, $e),
                $e instanceof BadRequestException && str_contains($e->getMessage(), 'credit balance') => new GenerationFailedException('Crédit Anthropic épuisé : rechargez le compte ou passez LLM_PROVIDER=groq.', 402, $e),
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
