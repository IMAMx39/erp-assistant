<?php

declare(strict_types=1);

namespace App\Embedding;

use App\Entity\Chunk;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Embeddings via l'API Ollama (/api/embed).
 *
 * nomic-embed-text attend un préfixe de tâche : « search_document: » pour les
 * passages indexés, « search_query: » pour les questions.
 */
final class OllamaEmbedder implements EmbedderInterface
{
    private const DOCUMENT_PREFIX = 'search_document: ';
    private const QUERY_PREFIX = 'search_query: ';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(OLLAMA_URL)%')]
        private readonly string $ollamaUrl,
        #[Autowire('%env(OLLAMA_EMBED_MODEL)%')]
        private readonly string $model,
        private readonly int $dimension = Chunk::EMBEDDING_DIMENSION,
    ) {
    }

    public function embedDocuments(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        return $this->embed(array_map(
            static fn (string $text): string => self::DOCUMENT_PREFIX.$text,
            $texts,
        ));
    }

    public function embedQuery(string $query): array
    {
        return $this->embed([self::QUERY_PREFIX.$query])[0];
    }

    /**
     * @param list<string> $inputs
     *
     * @return list<list<float>>
     */
    private function embed(array $inputs): array
    {
        $response = $this->httpClient->request('POST', rtrim($this->ollamaUrl, '/').'/api/embed', [
            'json' => ['model' => $this->model, 'input' => $inputs],
            'timeout' => 60,
        ]);

        /** @var list<list<float>> $embeddings */
        $embeddings = $response->toArray()['embeddings'] ?? [];

        if (\count($embeddings) !== \count($inputs)) {
            throw new \RuntimeException(sprintf(
                'Ollama a renvoyé %d embedding(s) pour %d texte(s).',
                \count($embeddings),
                \count($inputs),
            ));
        }

        foreach ($embeddings as $embedding) {
            if (\count($embedding) !== $this->dimension) {
                throw new \RuntimeException(sprintf(
                    'Dimension d\'embedding inattendue : %d au lieu de %d (modèle « %s »).',
                    \count($embedding),
                    $this->dimension,
                    $this->model,
                ));
            }
        }

        return $embeddings;
    }
}
