<?php

declare(strict_types=1);

namespace App\Chat;

use Anthropic\Client;
use Anthropic\Messages\CitationCharLocation;
use Anthropic\Messages\CitationsDelta;
use Anthropic\Messages\RawContentBlockDeltaEvent;
use Anthropic\Messages\RawMessageDeltaEvent;
use Anthropic\Messages\RawMessageStartEvent;
use Anthropic\Messages\TextDelta;
use App\Chat\Dto\ChatAnswer;
use App\Chat\Dto\ChatEvent;
use App\Chat\Dto\Citation;
use App\Retrieval\Dto\SearchResult;
use App\Retrieval\VectorRetriever;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * RAG : recherche des extraits pertinents, puis génération de la réponse citée par Claude.
 */
final class ChatService
{
    private const RETRIEVAL_LIMIT = 5;
    private const MAX_TOKENS = 16000;

    public function __construct(
        private readonly Client $anthropic,
        private readonly VectorRetriever $retriever,
        private readonly PromptBuilder $promptBuilder,
        #[Autowire('%env(ANTHROPIC_MODEL)%')]
        private readonly string $model,
    ) {
    }

    /**
     * Génère la réponse au fil de l'eau : sources, morceaux de texte, citations, puis fin.
     *
     * @return \Generator<int, ChatEvent>
     */
    public function stream(string $question): \Generator
    {
        $sources = $this->retriever->search($question, self::RETRIEVAL_LIMIT);
        yield new ChatEvent(ChatEvent::SOURCES, $sources);

        // Index vide : inutile d'appeler le modèle.
        if ($sources === []) {
            yield new ChatEvent(ChatEvent::DELTA, ['block' => 0, 'text' => PromptBuilder::NOT_FOUND_ANSWER]);
            yield new ChatEvent(ChatEvent::DONE, $this->done(false, null, 0, 0));

            return;
        }

        $stream = $this->anthropic->messages->createStream(
            maxTokens: self::MAX_TOKENS,
            messages: $this->promptBuilder->messages($question, $sources),
            model: $this->model,
            // Questions-réponses sur des extraits courts : peu de réflexion nécessaire.
            outputConfig: ['effort' => 'low'],
            system: $this->promptBuilder->system(),
        );

        $answer = '';
        $stopReason = null;
        $inputTokens = 0;
        $outputTokens = 0;

        foreach ($stream as $event) {
            if ($event instanceof RawMessageStartEvent) {
                $inputTokens = (int) $event->message->usage->inputTokens;
            } elseif ($event instanceof RawContentBlockDeltaEvent && $event->delta instanceof TextDelta) {
                $answer .= $event->delta->text;
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

        $answered = $stopReason !== 'refusal' && !str_contains($answer, PromptBuilder::NOT_FOUND_ANSWER);

        yield new ChatEvent(ChatEvent::DONE, $this->done($answered, $stopReason, $inputTokens, $outputTokens));
    }

    /**
     * Même génération, rassemblée en une seule réponse (API JSON, évaluations).
     */
    public function ask(string $question): ChatAnswer
    {
        $sources = [];
        $answer = '';
        $citations = [];
        $done = $this->done(false, null, 0, 0);

        foreach ($this->stream($question) as $event) {
            match ($event->type) {
                ChatEvent::SOURCES => $sources = $event->data,
                ChatEvent::DELTA => $answer .= $event->data['text'],
                ChatEvent::CITATION => $citations[] = $event->data,
                ChatEvent::DONE => $done = $event->data,
                default => null,
            };
        }

        return new ChatAnswer(
            $question,
            trim($answer),
            $done['answered'],
            $citations,
            $sources,
            $done['stopReason'],
            $done['inputTokens'],
            $done['outputTokens'],
        );
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

    /**
     * @return array{answered: bool, stopReason: ?string, inputTokens: int, outputTokens: int}
     */
    private function done(bool $answered, ?string $stopReason, int $inputTokens, int $outputTokens): array
    {
        return [
            'answered' => $answered,
            'stopReason' => $stopReason,
            'inputTokens' => $inputTokens,
            'outputTokens' => $outputTokens,
        ];
    }
}
