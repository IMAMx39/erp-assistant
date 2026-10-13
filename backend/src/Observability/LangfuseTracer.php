<?php

declare(strict_types=1);

namespace App\Observability;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Traces Langfuse au format OpenTelemetry (OTLP/HTTP en JSON, sans dépendance) : une trace par
 * question, faite d'une étape racine et de ses étapes imbriquées (recherche, appels au LLM, outils).
 *
 * Langfuse Cloud n'accepte plus que l'ingestion OpenTelemetry pour les traces (l'ancienne API
 * d'ingestion est coupée le 16/11/2026). Les attributs `langfuse.*` suivent sa table de correspondance.
 *
 * Inactif sans clés (aucun appel réseau). Les étapes sont gardées en mémoire et envoyées en un lot
 * par flush(), appelé après l'envoi de la réponse : l'utilisateur n'attend jamais Langfuse, et une
 * panne de Langfuse ne fait jamais échouer une question (elle est seulement journalisée).
 */
final class LangfuseTracer implements ResetInterface
{
    private ?string $traceId = null;
    /** @var array{spanId: string, name: string, start: string, attributes: array<string, mixed>}|null étape racine, close à endTrace() */
    private ?array $root = null;
    /** @var list<array<string, mixed>> étapes terminées, au format OTLP */
    private array $spans = [];
    /** @var list<string> identifiants des étapes en cours, pour l'imbrication */
    private array $parents = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(LANGFUSE_BASE_URL)%')]
        private readonly string $baseUrl,
        #[Autowire('%env(LANGFUSE_PUBLIC_KEY)%')]
        private readonly string $publicKey,
        #[Autowire('%env(LANGFUSE_SECRET_KEY)%')]
        private readonly string $secretKey,
    ) {
    }

    public function enabled(): bool
    {
        return $this->publicKey !== '' && $this->secretKey !== '';
    }

    /**
     * Ouvre la trace de la requête en cours (sans effet si une trace est déjà ouverte).
     *
     * @param array<string, mixed> $metadata
     * @param list<string>         $tags
     * @param string               $type     type Langfuse de l'étape racine (agent, span…)
     */
    public function startTrace(string $name, mixed $input, array $metadata = [], array $tags = [], string $type = 'span'): void
    {
        if (!$this->enabled() || $this->traceId !== null) {
            return;
        }

        $this->traceId = bin2hex(random_bytes(16));
        $attributes = [
            'langfuse.trace.name' => $name,
            'langfuse.observation.type' => $type,
            'langfuse.observation.input' => self::json($input),
        ];
        if ($tags !== []) {
            $attributes['langfuse.trace.tags'] = $tags;
        }
        foreach ($metadata as $key => $value) {
            $attributes['langfuse.trace.metadata.'.$key] = $value;
        }

        $this->root = ['spanId' => self::spanId(), 'name' => $name, 'start' => self::now(), 'attributes' => $attributes];
        $this->parents = [$this->root['spanId']];
    }

    /**
     * Ferme l'étape racine avec la sortie de la trace.
     *
     * @param array<string, mixed> $metadata
     */
    public function endTrace(mixed $output, array $metadata = []): void
    {
        if ($this->root === null) {
            return;
        }

        $attributes = $this->root['attributes'];
        $attributes['langfuse.observation.output'] = self::json($output);
        foreach ($metadata as $key => $value) {
            $attributes['langfuse.trace.metadata.'.$key] = $value;
        }

        $this->spans[] = $this->otlpSpan($this->root['spanId'], null, $this->root['name'], $this->root['start'], self::now(), $attributes);
        $this->root = null;
    }

    /**
     * Exécute $work en l'enregistrant comme étape ; les étapes ouvertes pendant $work y sont imbriquées.
     *
     * @template T
     *
     * @param callable(): T        $work
     * @param ?callable(T): mixed  $describe transforme le résultat en sortie lisible dans Langfuse
     * @param array<string, mixed> $metadata
     * @param string               $type     type Langfuse : span, retriever, tool…
     *
     * @return T
     */
    public function span(string $name, mixed $input, callable $work, ?callable $describe = null, array $metadata = [], string $type = 'span'): mixed
    {
        if ($this->traceId === null) {
            return $work();
        }

        $id = self::spanId();
        $parent = $this->currentParent();
        $start = self::now();
        $this->parents[] = $id;
        $attributes = ['langfuse.observation.type' => $type, 'langfuse.observation.input' => self::json($input)];
        foreach ($metadata as $key => $value) {
            $attributes['langfuse.observation.metadata.'.$key] = $value;
        }

        try {
            $result = $work();
        } catch (\Throwable $e) {
            $attributes['langfuse.observation.level'] = 'ERROR';
            $attributes['langfuse.observation.status_message'] = $e->getMessage();
            $this->spans[] = $this->otlpSpan($id, $parent, $name, $start, self::now(), $attributes, $e->getMessage());

            throw $e;
        } finally {
            array_pop($this->parents);
        }

        $attributes['langfuse.observation.output'] = self::json($describe === null ? $result : $describe($result));
        $this->spans[] = $this->otlpSpan($id, $parent, $name, $start, self::now(), $attributes);

        return $result;
    }

    /**
     * Enregistre un appel à un LLM déjà terminé (les tours streamés ne se prêtent pas à span()).
     *
     * @param string               $startTime           horodatage de LangfuseTracer::now()
     * @param ?string              $completionStartTime horodatage du premier token reçu
     * @param array<string, mixed> $metadata
     */
    public function generation(
        string $name,
        string $model,
        mixed $input,
        mixed $output,
        string $startTime,
        ?string $completionStartTime,
        int $inputTokens,
        int $outputTokens,
        array $metadata = [],
    ): void {
        if ($this->traceId === null) {
            return;
        }

        $attributes = [
            'langfuse.observation.type' => 'generation',
            'langfuse.observation.model.name' => $model,
            'langfuse.observation.input' => self::json($input),
            'langfuse.observation.output' => self::json($output),
            'langfuse.observation.usage_details' => self::json(['input' => $inputTokens, 'output' => $outputTokens, 'total' => $inputTokens + $outputTokens]),
        ];
        if ($completionStartTime !== null) {
            $attributes['langfuse.observation.completion_start_time'] = self::iso($completionStartTime);
        }
        foreach ($metadata as $key => $value) {
            $attributes['langfuse.observation.metadata.'.$key] = $value;
        }

        $this->spans[] = $this->otlpSpan(self::spanId(), $this->currentParent(), $name, $startTime, self::now(), $attributes);
    }

    /**
     * Envoie les étapes en attente en un seul lot OTLP, puis repart de zéro. Une trace dont
     * l'étape racine n'a pas été fermée (exception en cours de route) est close en erreur.
     */
    public function flush(): void
    {
        if ($this->root !== null) {
            $this->root['attributes']['langfuse.observation.level'] = 'ERROR';
            $this->spans[] = $this->otlpSpan($this->root['spanId'], null, $this->root['name'], $this->root['start'], self::now(), $this->root['attributes'], 'Trace interrompue avant sa fin');
        }

        $spans = $this->spans;
        $this->reset();

        if ($spans === [] || !$this->enabled()) {
            return;
        }

        try {
            $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/').'/api/public/otel/v1/traces', [
                'auth_basic' => [$this->publicKey, $this->secretKey],
                'headers' => ['x-langfuse-ingestion-version' => '4'],
                'json' => ['resourceSpans' => [[
                    'resource' => ['attributes' => self::attributes(['service.name' => 'erp-assistant'])],
                    'scopeSpans' => [['scope' => ['name' => 'erp-assistant'], 'spans' => $spans]],
                ]]],
                'timeout' => 10,
            ]);

            $status = $response->getStatusCode();
            $body = $response->toArray(false);
            $rejected = (int) ($body['partialSuccess']['rejectedSpans'] ?? 0);
            if ($status >= 300 || $rejected > 0) {
                $this->logger->warning(sprintf(
                    'Langfuse a refusé des étapes (HTTP %d, %d rejetée(s)) : %s',
                    $status,
                    $rejected,
                    json_encode($body, \JSON_UNESCAPED_UNICODE),
                ));
            }
        } catch (HttpClientException $e) {
            $this->logger->warning(sprintf('Envoi des traces Langfuse impossible : %s', $e->getMessage()));
        }
    }

    public function reset(): void
    {
        $this->traceId = null;
        $this->root = null;
        $this->spans = [];
        $this->parents = [];
    }

    /**
     * Horodatage en nanosecondes depuis l'époque Unix (format OTLP).
     */
    public static function now(): string
    {
        return sprintf('%d000', (int) round(microtime(true) * 1_000_000));
    }

    private function currentParent(): ?string
    {
        return $this->parents === [] ? null : $this->parents[\count($this->parents) - 1];
    }

    /**
     * @param array<string, mixed> $attributes
     *
     * @return array<string, mixed>
     */
    private function otlpSpan(string $spanId, ?string $parentId, string $name, string $start, string $end, array $attributes, ?string $error = null): array
    {
        return array_filter([
            'traceId' => $this->traceId,
            'spanId' => $spanId,
            'parentSpanId' => $parentId,
            'name' => $name,
            'kind' => 1, // SPAN_KIND_INTERNAL
            'startTimeUnixNano' => $start,
            'endTimeUnixNano' => $end,
            'attributes' => self::attributes($attributes),
            'status' => $error === null ? ['code' => 1] : ['code' => 2, 'message' => $error],
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * Attributs OTLP typés : chaînes, entiers, booléens, listes de chaînes ; le reste en JSON.
     *
     * @param array<string, mixed> $attributes
     *
     * @return list<array{key: string, value: array<string, mixed>}>
     */
    private static function attributes(array $attributes): array
    {
        $otlp = [];
        foreach ($attributes as $key => $value) {
            if ($value === null) {
                continue;
            }
            $otlp[] = ['key' => $key, 'value' => match (true) {
                \is_bool($value) => ['boolValue' => $value],
                \is_int($value) => ['intValue' => (string) $value],
                \is_float($value) => ['doubleValue' => $value],
                \is_string($value) => ['stringValue' => $value],
                \is_array($value) && array_is_list($value) && array_filter($value, 'is_string') === $value => [
                    'arrayValue' => ['values' => array_map(static fn (string $v): array => ['stringValue' => $v], $value)],
                ],
                default => ['stringValue' => self::json($value)],
            }];
        }

        return $otlp;
    }

    private static function json(mixed $value): string
    {
        return \is_string($value) ? $value : (string) json_encode($value, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PARTIAL_OUTPUT_ON_ERROR);
    }

    private static function iso(string $unixNano): string
    {
        $microseconds = intdiv((int) $unixNano, 1000);

        return \DateTimeImmutable::createFromFormat('U.u', sprintf('%d.%06d', intdiv($microseconds, 1_000_000), $microseconds % 1_000_000), new \DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s.u\Z');
    }

    private static function spanId(): string
    {
        return bin2hex(random_bytes(8));
    }
}
