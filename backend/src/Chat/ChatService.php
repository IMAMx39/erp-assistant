<?php

declare(strict_types=1);

namespace App\Chat;

use App\Chat\Dto\ChatAnswer;
use App\Chat\Dto\ChatEvent;
use App\Chat\Exception\GenerationFailedException;
use App\Chat\Generator\AnswerGeneratorInterface;
use App\Retrieval\DocumentRetriever;
use App\Retrieval\RetrievalMode;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;

/**
 * RAG : recherche des extraits pertinents, puis rédaction de la réponse citée par le LLM
 * choisi avec LLM_PROVIDER (anthropic, groq, mistral ou ollama).
 */
final class ChatService
{
    private const RETRIEVAL_LIMIT = 5;

    public function __construct(
        private readonly DocumentRetriever $retriever,
        #[AutowireLocator(AnswerGeneratorInterface::class, indexAttribute: 'key')]
        private readonly ContainerInterface $generators,
        #[Autowire('%env(LLM_PROVIDER)%')]
        private readonly string $provider,
    ) {
    }

    /**
     * Génère la réponse au fil de l'eau : sources, morceaux de texte, citations, puis fin.
     *
     * @return \Generator<int, ChatEvent>
     *
     * @throws GenerationFailedException
     */
    public function stream(string $question, ?RetrievalMode $mode = null): \Generator
    {
        $generator = $this->generator();
        $sources = $this->retriever->search($question, self::RETRIEVAL_LIMIT, $mode);
        yield new ChatEvent(ChatEvent::SOURCES, $sources);

        // Index vide : inutile d'appeler le modèle.
        if ($sources === []) {
            yield new ChatEvent(ChatEvent::DELTA, ['block' => 0, 'text' => PromptBuilder::NOT_FOUND_ANSWER]);
            yield new ChatEvent(ChatEvent::DONE, $this->done(false, null, null, 0, 0));

            return;
        }

        $answer = '';
        $generation = $generator->generate($question, $sources);

        foreach ($generation as $event) {
            if ($event->type === ChatEvent::DELTA) {
                $answer .= $event->data['text'];
            }
            yield $event;
        }

        $result = $generation->getReturn();
        $answered = $result->stopReason !== 'refusal' && !str_contains($answer, PromptBuilder::NOT_FOUND_ANSWER);

        yield new ChatEvent(ChatEvent::DONE, $this->done(
            $answered,
            $result->model,
            $result->stopReason,
            $result->inputTokens,
            $result->outputTokens,
        ));
    }

    /**
     * Même génération, rassemblée en une seule réponse (API JSON, évaluations).
     *
     * @throws GenerationFailedException
     */
    public function ask(string $question, ?RetrievalMode $mode = null): ChatAnswer
    {
        $sources = [];
        $answer = '';
        $citations = [];
        $tools = [];
        $done = $this->done(false, null, null, 0, 0);

        foreach ($this->stream($question, $mode) as $event) {
            match ($event->type) {
                ChatEvent::SOURCES => $sources = $event->data,
                ChatEvent::DELTA => $answer .= $event->data['text'],
                ChatEvent::CITATION => $citations[] = $event->data,
                ChatEvent::TOOL => $tools[] = $event->data,
                ChatEvent::DONE => $done = $event->data,
                default => null,
            };
        }

        return new ChatAnswer(
            $question,
            trim($answer),
            $done['answered'],
            $citations,
            $tools,
            $sources,
            $done['model'],
            $done['stopReason'],
            $done['inputTokens'],
            $done['outputTokens'],
        );
    }

    private function generator(): AnswerGeneratorInterface
    {
        if (!$this->generators->has($this->provider)) {
            throw new GenerationFailedException(sprintf('Fournisseur de LLM inconnu : « %s » (LLM_PROVIDER = anthropic, groq, mistral ou ollama).', $this->provider), 500);
        }

        return $this->generators->get($this->provider);
    }

    /**
     * @return array{answered: bool, model: ?string, stopReason: ?string, inputTokens: int, outputTokens: int}
     */
    private function done(bool $answered, ?string $model, ?string $stopReason, int $inputTokens, int $outputTokens): array
    {
        return [
            'answered' => $answered,
            'model' => $model,
            'stopReason' => $stopReason,
            'inputTokens' => $inputTokens,
            'outputTokens' => $outputTokens,
        ];
    }
}
