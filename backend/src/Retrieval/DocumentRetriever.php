<?php

declare(strict_types=1);

namespace App\Retrieval;

use App\Observability\LangfuseTracer;
use App\Retrieval\Dto\SearchResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Point d'entrée de la recherche documentaire, selon le mode choisi (RETRIEVAL_MODE ou par appel).
 *
 * Mode hybride : les 20 meilleurs résultats de chaque méthode sont fusionnés par RRF
 * (Reciprocal Rank Fusion) : score = Σ 1 / (K + rang). Seuls les rangs comptent, ce qui évite
 * de comparer une distance cosinus et un score plein texte, d'échelles différentes.
 */
final class DocumentRetriever
{
    private const CANDIDATES = 20;
    private const RRF_K = 60;
    private const RERANK_POOL = 10;

    public function __construct(
        private readonly VectorRetriever   $vectorRetriever,
        private readonly FulltextRetriever $fulltextRetriever,
        private readonly LlmReranker       $reranker,
        #[Autowire('%env(RETRIEVAL_MODE)%')]
        private readonly string            $defaultMode,
        private readonly LangfuseTracer $tracer,
    )
    {
    }

    /**
     * @return list<SearchResult>
     */
    public function search(string $query, int $limit = 5, ?RetrievalMode $mode = null): array
    {
        $mode ??= $this->defaultMode();

        return $this->tracer->span(
            'recherche',
            ['question' => $query, 'mode' => $mode->value, 'limite' => $limit],
            fn (): array => match ($mode) {
                RetrievalMode::Vector => $this->vectorRetriever->search($query, $limit),
                RetrievalMode::Hybrid => $this->hybrid($query, $limit),
                RetrievalMode::HybridRerank => $this->reranker->rerank($query, $this->hybrid($query, self::RERANK_POOL), $limit),
            },
            self::describe(...),
        );
    }

    /**
     * Résumé lisible d'une liste de résultats pour les traces.
     *
     * @param list<SearchResult> $results
     *
     * @return list<string>
     */
    private static function describe(array $results): array
    {
        return array_map(static fn (SearchResult $r): string => sprintf('%.4f  %s', $r->score, $r->heading), $results);
    }

    public function defaultMode(): RetrievalMode
    {
        return RetrievalMode::tryFrom($this->defaultMode) ?? throw new \InvalidArgumentException(sprintf(
            'RETRIEVAL_MODE invalide : « %s » (valeurs possibles : %s).',
            $this->defaultMode,
            implode(', ', RetrievalMode::values()),
        ));
    }

    /**
     * @return list<SearchResult>
     */
    private function hybrid(string $query, int $limit): array
    {
        /** @var array<int, SearchResult> $results */
        $results = [];
        /** @var array<int, float> $scores */
        $scores = [];

        $rankings = [
            $this->tracer->span('vecteurs', $query, fn (): array => $this->vectorRetriever->search($query, self::CANDIDATES), self::describe(...)),
            $this->tracer->span('plein texte', $query, fn (): array => $this->fulltextRetriever->search($query, self::CANDIDATES), self::describe(...)),
        ];

        foreach ($rankings as $ranking) {
            foreach ($ranking as $rank => $result) {
                // On garde la version vectorielle d'un chunk (elle porte la distance).
                $results[$result->chunkId] ??= $result;
                $scores[$result->chunkId] = ($scores[$result->chunkId] ?? 0.0) + 1 / (self::RRF_K + $rank + 1);
            }
        }

        arsort($scores);

        $fused = [];
        foreach (\array_slice($scores, 0, $limit, true) as $chunkId => $score) {
            $fused[] = $results[$chunkId]->withScore($score);
        }

        return $fused;
    }
}
