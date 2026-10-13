<?php

declare(strict_types=1);

namespace App\Retrieval;

use App\Repository\ChunkRepository;
use App\Retrieval\Dto\SearchResult;
use Doctrine\DBAL\Connection;

/**
 * Recherche plein texte MariaDB (index FULLTEXT sur l'intitulé et le contenu des chunks).
 *
 * Complète la recherche vectorielle sur les termes exacts (« litige », « mise en demeure »).
 * La liste de mots vides d'InnoDB est anglaise : « les », « une », « peut »… sont indexés et
 * correspondent à presque tous les chunks. On les retire donc de la question avant la recherche
 * (InnoDB ignore de toute façon les mots de moins de 3 lettres).
 */
final class FulltextRetriever
{
    /**
     * Mots vides français et mots interrogatifs, sans les termes métier homonymes (« avoir »).
     */
    private const STOPWORDS = [
        'alors', 'apres', 'après', 'au', 'aucun', 'aucune', 'aussi', 'autre', 'aux', 'avant', 'avec', 'bien', 'car',
        'ce', 'cela', 'ces', 'cet', 'cette', 'chaque', 'chez', 'combien', 'comme', 'comment', 'dans', 'des',
        'doit', 'doivent', 'donc', 'dont', 'elle', 'elles', 'encore', 'entre', 'est', 'est-ce', 'etre', 'être',
        'fait', 'faire', 'faut', 'leur', 'leurs', 'lors', 'mais', 'mes', 'moi', 'mon', 'nos', 'notre', 'nous',
        'ont', 'par', 'pas', 'peut', 'peuvent', 'plus', 'pour', 'pourquoi', 'quand', 'que', 'quel', 'quelle',
        'quelles', 'quels', 'qui', 'quoi', 'sans', 'ses', 'son', 'sont', 'sous', 'sur', 'tes', 'ton', 'tous',
        'tout', 'toute', 'toutes', 'tres', 'très', 'une', 'vos', 'votre', 'vous', 'les',
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly ChunkRepository $chunkRepository,
    ) {
    }

    /**
     * @return list<SearchResult> triés par score de pertinence décroissant
     */
    public function search(string $query, int $limit = 20): array
    {
        $query = $this->keywords($query);
        if ($query === '') {
            return [];
        }

        /** @var list<array{id: int|string, score: float|string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, MATCH(heading, content) AGAINST(:query IN NATURAL LANGUAGE MODE) AS score
             FROM chunk
             WHERE MATCH(heading, content) AGAINST(:query IN NATURAL LANGUAGE MODE)
             ORDER BY score DESC
             LIMIT '.max(1, $limit),
            ['query' => $query],
        );

        $scores = [];
        foreach ($rows as $row) {
            $scores[(int) $row['id']] = (float) $row['score'];
        }

        $chunks = $this->chunkRepository->findWithDocuments(array_keys($scores));

        return array_map(
            static fn ($chunk): SearchResult => SearchResult::fromChunk($chunk, null, $scores[(int) $chunk->getId()]),
            $chunks,
        );
    }

    /**
     * Mots porteurs de sens de la question : sans ponctuation, sans mots vides ni mots de moins de 3 lettres.
     */
    private function keywords(string $query): string
    {
        $words = preg_split('/[^\p{L}\p{N}-]+/u', mb_strtolower($query), flags: \PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_filter(
            $words,
            static fn (string $word): bool => mb_strlen($word) >= 3 && !\in_array($word, self::STOPWORDS, true),
        ));
    }
}
