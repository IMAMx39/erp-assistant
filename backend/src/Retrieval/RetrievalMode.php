<?php

declare(strict_types=1);

namespace App\Retrieval;

enum RetrievalMode: string
{
    /** Recherche vectorielle seule. */
    case Vector = 'vector';

    /** Vecteurs + plein texte, fusion RRF. */
    case Hybrid = 'hybrid';

    /** Hybride, puis reclassement des meilleurs candidats par un LLM. */
    case HybridRerank = 'hybrid_rerank';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn(self $mode): string => $mode->value, self::cases());
    }
}
