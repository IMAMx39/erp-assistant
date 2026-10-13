<?php

declare(strict_types=1);

namespace App\Chat\Generator;

use App\Chat\Dto\ChatEvent;
use App\Chat\Dto\Citation;
use App\Chat\Dto\GenerationResult;
use App\Chat\Exception\GenerationFailedException;
use App\Chat\PromptBuilder;
use App\Retrieval\Dto\SearchResult;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Mistral via l'API Chat Completions en streaming.
 *
 * Pas de citations natives : le modèle cite avec des marqueurs [n] que l'on retire du texte
 * et transforme en événements CITATION. Chaque marqueur clôt un bloc de texte, comme les
 * blocs cités de Claude, pour que l'interface les affiche de la même façon.
 */
#[AsTaggedItem('mistral')]
final class MistralAnswerGenerator implements AnswerGeneratorInterface
{
    private const API_URL = 'https://api.mistral.ai/v1/chat/completions';
    private const MAX_TOKENS = 2048;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly PromptBuilder $promptBuilder,
        #[Autowire('%env(MISTRAL_API_KEY)%')]
        private readonly string $apiKey,
        #[Autowire('%env(MISTRAL_MODEL)%')]
        private readonly string $model,
    ) {
    }

    public function generate(string $question, array $sources): \Generator
    {
        if ($this->apiKey === '') {
            throw new GenerationFailedException('Clé API Mistral absente (MISTRAL_API_KEY).', 500);
        }

        $parser = new CitationMarkerParser();
        $block = 0;
        $stopReason = null;
        $inputTokens = 0;
        $outputTokens = 0;

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'auth_bearer' => $this->apiKey,
                'headers' => ['Accept' => 'text/event-stream'],
                'json' => [
                    'model' => $this->model,
                    'stream' => true,
                    'temperature' => 0.2,
                    'max_tokens' => self::MAX_TOKENS,
                    'messages' => [
                        ['role' => 'system', 'content' => $this->promptBuilder->system(inlineMarkers: true)],
                        ['role' => 'user', 'content' => $this->promptBuilder->numberedSourcesMessage($question, $sources)],
                    ],
                ],
                'timeout' => 60,
            ]);

            $status = $response->getStatusCode();
            if ($status !== 200) {
                throw match ($status) {
                    401 => new GenerationFailedException('Clé API Mistral invalide (MISTRAL_API_KEY).', 500),
                    429 => new GenerationFailedException('Trop de demandes en cours, réessayez dans quelques instants.', 503),
                    default => new GenerationFailedException(sprintf('Mistral a renvoyé une erreur (HTTP %d).', $status), 502),
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

                    /** @var array{choices?: list<array{delta?: array{content?: mixed}, finish_reason?: ?string}>, usage?: array{prompt_tokens?: int, completion_tokens?: int}} $payload */
                    $payload = json_decode($data, true, flags: \JSON_THROW_ON_ERROR);
                    $choice = $payload['choices'][0] ?? [];

                    foreach ($parser->feed($this->text($choice['delta']['content'] ?? '')) as $token) {
                        yield from $this->events($token, $block, $sources);
                    }

                    $stopReason = $choice['finish_reason'] ?? $stopReason;
                    $inputTokens = $payload['usage']['prompt_tokens'] ?? $inputTokens;
                    $outputTokens = $payload['usage']['completion_tokens'] ?? $outputTokens;
                }
            }

            foreach ($parser->flush() as $token) {
                yield from $this->events($token, $block, $sources);
            }
        } catch (TransportExceptionInterface $e) {
            throw new GenerationFailedException('Le service Mistral est injoignable.', 503, $e);
        } catch (\JsonException $e) {
            throw new GenerationFailedException('Réponse illisible du service Mistral.', 502, $e);
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
