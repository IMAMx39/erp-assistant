<?php

declare(strict_types=1);

namespace App\Retrieval;

use App\Retrieval\Dto\SearchResult;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reclasse les candidats de la recherche hybride avec un LLM (API Chat Completions, Groq par défaut).
 *
 * Le LLM lit la question et les extraits, et renvoie leurs numéros du plus au moins pertinent.
 * En cas d'échec (quota, réponse illisible), l'ordre d'origine est conservé : le reclassement
 * améliore la recherche mais ne doit jamais la faire échouer.
 */
final class LlmReranker
{
    private const EXCERPT_LENGTH = 800;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $url,
        private readonly string $model,
        private readonly string $apiKey,
    ) {
    }

    /**
     * @param list<SearchResult> $candidates
     *
     * @return list<SearchResult> les $limit meilleurs, score = 1 pour le premier, 1/2 pour le deuxième…
     */
    public function rerank(string $query, array $candidates, int $limit): array
    {
        if (\count($candidates) <= 1) {
            return \array_slice($candidates, 0, $limit);
        }

        try {
            $order = $this->ranking($query, $candidates);
        } catch (HttpClientException|\JsonException|\UnexpectedValueException $e) {
            $this->logger->warning('Reclassement impossible, ordre de la recherche hybride conservé', ['exception' => $e]);
            $order = array_keys($candidates);
        }

        $ranked = [];
        foreach (\array_slice($order, 0, $limit) as $rank => $index) {
            $ranked[] = $candidates[$index]->withScore(1 / ($rank + 1));
        }

        return $ranked;
    }

    /**
     * @param list<SearchResult> $candidates
     *
     * @return list<int> index des candidats, du plus au moins pertinent
     */
    private function ranking(string $query, array $candidates): array
    {
        $extracts = [];
        foreach ($candidates as $i => $candidate) {
            $extracts[] = sprintf("[%d] %s\n%s", $i + 1, $candidate->heading, mb_substr($candidate->content, 0, self::EXCERPT_LENGTH));
        }

        $response = $this->httpClient->request('POST', $this->url, [
            'auth_bearer' => $this->apiKey,
            'json' => [
                'model' => $this->model,
                'temperature' => 0,
                'max_tokens' => 200,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Tu classes des extraits de documentation selon leur utilité pour répondre à une question. '
                            .'Réponds uniquement avec un objet JSON {"ranking": [...]} listant les numéros de tous les extraits, '
                            .'du plus utile au moins utile.',
                    ],
                    [
                        'role' => 'user',
                        'content' => "Question : {$query}\n\nExtraits :\n\n".implode("\n\n", $extracts),
                    ],
                ],
            ],
            'timeout' => 30,
        ]);

        /** @var array{choices?: list<array{message?: array{content?: string}}>} $body */
        $body = $response->toArray();
        $content = $body['choices'][0]['message']['content'] ?? '';
        /** @var array{ranking?: mixed} $decoded */
        $decoded = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);

        if (!\is_array($decoded['ranking'] ?? null)) {
            throw new \UnexpectedValueException(sprintf('Classement absent de la réponse : %s', $content));
        }

        // Numéros valides et uniques, puis les candidats oubliés par le LLM, dans leur ordre d'origine.
        $order = [];
        foreach ($decoded['ranking'] as $number) {
            $index = (int) $number - 1;
            if (isset($candidates[$index]) && !\in_array($index, $order, true)) {
                $order[] = $index;
            }
        }

        foreach (array_keys($candidates) as $index) {
            if (!\in_array($index, $order, true)) {
                $order[] = $index;
            }
        }

        return $order;
    }
}
