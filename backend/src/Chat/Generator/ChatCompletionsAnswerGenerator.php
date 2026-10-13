<?php

declare(strict_types=1);

namespace App\Chat\Generator;

use App\Chat\Dto\ChatEvent;
use App\Chat\Dto\Citation;
use App\Chat\Dto\GenerationResult;
use App\Chat\Exception\GenerationFailedException;
use App\Chat\PromptBuilder;
use App\Retrieval\Dto\SearchResult;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fournisseur compatible avec l'API Chat Completions d'OpenAI, en streaming :
 * Groq, Mistral, Ollama… Une instance par fournisseur est déclarée dans services.yaml.
 *
 * Pas de citations natives : le modèle cite avec des marqueurs [n] que l'on retire du texte
 * et transforme en événements CITATION. Chaque marqueur clôt un bloc de texte, comme les
 * blocs cités de Claude, pour que l'interface les affiche de la même façon.
 */
final class ChatCompletionsAnswerGenerator implements AnswerGeneratorInterface
{
    private const MAX_TOKENS = 2048;

    /**
     * @param string  $provider   nom affiché dans les messages d'erreur
     * @param ?string $keyEnvName   variable de la clé API, null si le fournisseur n'en demande pas
     * @param bool    $requestUsage envoie stream_options.include_usage (Ollama) ; d'autres refusent ce champ
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly PromptBuilder $promptBuilder,
        private readonly string $provider,
        private readonly string $url,
        private readonly string $model,
        private readonly string $apiKey = '',
        private readonly ?string $keyEnvName = null,
        private readonly bool $requestUsage = false,
    ) {
    }

    public function generate(string $question, array $sources): \Generator
    {
        if ($this->keyEnvName !== null && $this->apiKey === '') {
            throw new GenerationFailedException(sprintf('Clé API %s absente (%s).', $this->provider, $this->keyEnvName), 500);
        }

        $parser = new CitationMarkerParser();
        $block = 0;
        $stopReason = null;
        $inputTokens = 0;
        $outputTokens = 0;

        try {
            $body = [
                'model' => $this->model,
                'stream' => true,
                'temperature' => 0.2,
                'max_tokens' => self::MAX_TOKENS,
                'messages' => [
                    ['role' => 'system', 'content' => $this->promptBuilder->system(inlineMarkers: true)],
                    ['role' => 'user', 'content' => $this->promptBuilder->numberedSourcesMessage($question, $sources)],
                ],
            ];
            if ($this->requestUsage) {
                $body['stream_options'] = ['include_usage' => true];
            }

            $response = $this->httpClient->request('POST', $this->url, [
                'auth_bearer' => $this->apiKey !== '' ? $this->apiKey : null,
                'headers' => ['Accept' => 'text/event-stream'],
                'json' => $body,
                'timeout' => 60,
            ]);

            $status = $response->getStatusCode();
            if ($status !== 200) {
                throw match ($status) {
                    401 => new GenerationFailedException(sprintf('Clé API %s invalide (%s).', $this->provider, $this->keyEnvName ?? 'aucune clé attendue'), 500),
                    404 => new GenerationFailedException(sprintf('Modèle « %s » introuvable chez %s.', $this->model, $this->provider), 502),
                    429 => new GenerationFailedException(sprintf('Limite de requêtes %s atteinte, réessayez dans quelques instants.', $this->provider), 503),
                    default => new GenerationFailedException(sprintf('%s a renvoyé une erreur (HTTP %d).', $this->provider, $status), 502),
                };
            }

            $buffer = '';
            foreach ($this->httpClient->stream($response) as $chunk) {
                $buffer .= $chunk->getContent();

                while (($end = strpos($buffer, "\n")) !== false) {
                    $line = trim(substr($buffer, 0, $end));
                    $buffer = substr($buffer, $end + 1);

                    if (!str_starts_with($line, 'data:') || ($data = trim(substr($line, 5))) === '[DONE]') {
                        continue;
                    }

                    /** @var array{choices?: list<array{delta?: array{content?: mixed}, finish_reason?: ?string}>, usage?: array{prompt_tokens?: int, completion_tokens?: int}, x_groq?: array{usage?: array{prompt_tokens?: int, completion_tokens?: int}}} $payload */
                    $payload = json_decode($data, true, flags: \JSON_THROW_ON_ERROR);
                    $choice = $payload['choices'][0] ?? [];
                    // Groq place les tokens consommés dans x_groq.usage.
                    $usage = $payload['usage'] ?? $payload['x_groq']['usage'] ?? [];

                    foreach ($parser->feed($this->text($choice['delta']['content'] ?? '')) as $token) {
                        yield from $this->events($token, $block, $sources);
                    }

                    $stopReason = $choice['finish_reason'] ?? $stopReason;
                    $inputTokens = $usage['prompt_tokens'] ?? $inputTokens;
                    $outputTokens = $usage['completion_tokens'] ?? $outputTokens;
                }
            }

            foreach ($parser->flush() as $token) {
                yield from $this->events($token, $block, $sources);
            }
        } catch (TransportExceptionInterface $e) {
            throw new GenerationFailedException(sprintf('Le service %s est injoignable.', $this->provider), 503, $e);
        } catch (\JsonException $e) {
            throw new GenerationFailedException(sprintf('Réponse illisible du service %s.', $this->provider), 502, $e);
        }

        return new GenerationResult($this->model, $stopReason, $inputTokens, $outputTokens);
    }

    /**
     * @param array{type: 'text', text: string}|array{type: 'cite', sources: list<int>} $token
     * @param list<SearchResult>                                                        $sources
     *
     * @return \Generator<int, ChatEvent>
     */
    private function events(array $token, int &$block, array $sources): \Generator
    {
        if ($token['type'] === 'text') {
            yield new ChatEvent(ChatEvent::DELTA, ['block' => $block, 'text' => $token['text']]);

            return;
        }

        foreach (array_unique($token['sources']) as $number) {
            $source = $sources[$number - 1] ?? null;
            // Numéro hors des extraits fournis : marqueur halluciné, ignoré.
            if ($source !== null) {
                yield new ChatEvent(ChatEvent::CITATION, new Citation($number, $source->chunkId, $source->documentPath, '', $block));
            }
        }

        ++$block;
    }

    /**
     * Le contenu est une chaîne, ou une liste de morceaux typés pour certains modèles.
     */
    private function text(mixed $content): string
    {
        if (\is_string($content)) {
            return $content;
        }

        if (!\is_array($content)) {
            return '';
        }

        return implode('', array_map(
            static fn (mixed $part): string => \is_array($part) && ($part['type'] ?? null) === 'text' ? (string) ($part['text'] ?? '') : '',
            $content,
        ));
    }
}
