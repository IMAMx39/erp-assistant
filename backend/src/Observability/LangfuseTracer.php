<?php

declare(strict_types=1);

namespace App\Observability;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Traces Langfuse (API d'ingestion) : une trace par question, avec ses étapes (recherche, appels
 * au LLM, outils) imbriquées.
 *
 * Inactif sans clés (aucun appel réseau). Les événements sont gardés en mémoire et envoyés en un
 * lot par flush(), appelé après l'envoi de la réponse : l'utilisateur n'attend jamais Langfuse, et
 * une panne de Langfuse ne fait jamais échouer une question (elle est seulement journalisée).
 */
final class LangfuseTracer implements ResetInterface
{
    private ?string $traceId = null;
    /** @var list<array<string, mixed>> */
    private array $events = [];
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
     */
    public function startTrace(string $name, mixed $input, array $metadata = [], array $tags = []): void
    {
        if (!$this->enabled() || $this->traceId !== null) {
            return;
        }

        $this->traceId = self::uuid();
        $this->record('trace-create', [
            'id' => $this->traceId,
            'name' => $name,
            'input' => $input,
            'metadata' => $metadata,
            'tags' => $tags,
            'timestamp' => self::now(),
        ]);
    }

    /**
     * Complète la trace (même identifiant : Langfuse fusionne les champs).
     *
     * @param array<string, mixed> $metadata
     */
    public function endTrace(mixed $output, array $metadata = []): void
    {
        if ($this->traceId === null) {
            return;
        }

        $this->record('trace-create', array_filter([
            'id' => $this->traceId,
            'output' => $output,
            'metadata' => $metadata === [] ? null : $metadata,
        ], static fn (mixed $value): bool => $value !== null));
    }

    /**
     * Exécute $work en l'enregistrant comme étape ; les étapes ouvertes pendant $work y sont imbriquées.
     *
     * @template T
     *
     * @param callable(): T            $work
     * @param ?callable(T): mixed      $describe transforme le résultat en sortie lisible dans Langfuse
     * @param array<string, mixed>     $metadata
     *
     * @return T
     */
    public function span(string $name, mixed $input, callable $work, ?callable $describe = null, array $metadata = []): mixed
    {
        if ($this->traceId === null) {
            return $work();
        }

        $id = self::uuid();
        $parent = $this->parents === [] ? null : $this->parents[\count($this->parents) - 1];
        $start = self::now();
        $this->parents[] = $id;

        try {
            $result = $work();
        } catch (\Throwable $e) {
            $this->recordSpan($id, $parent, $name, $input, null, $start, $metadata, 'ERROR', $e->getMessage());

            throw $e;
        } finally {
            array_pop($this->parents);
        }

        $this->recordSpan($id, $parent, $name, $input, $describe === null ? $result : $describe($result), $start, $metadata);

        return $result;
    }

    /**
     * Enregistre un appel à un LLM déjà terminé (les tours streamés ne se prêtent pas à span()).
     *
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

        $this->record('generation-create', array_filter([
            'id' => self::uuid(),
            'traceId' => $this->traceId,
            'parentObservationId' => $this->parents === [] ? null : $this->parents[\count($this->parents) - 1],
            'name' => $name,
            'model' => $model,
            'input' => $input,
            'output' => $output,
            'startTime' => $startTime,
            'completionStartTime' => $completionStartTime,
            'endTime' => self::now(),
            'usageDetails' => ['input' => $inputTokens, 'output' => $outputTokens],
            'metadata' => $metadata === [] ? null : $metadata,
        ], static fn (mixed $value): bool => $value !== null));
    }

    /**
     * Envoie les événements en attente en un seul lot, puis repart de zéro.
     */
    public function flush(): void
    {
        $events = $this->events;
        $this->reset();

        if ($events === [] || !$this->enabled()) {
            return;
        }

        try {
            $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/').'/api/public/ingestion', [
                'auth_basic' => [$this->publicKey, $this->secretKey],
                'json' => ['batch' => $events],
                'timeout' => 10,
            ]);

            // 207 : chaque événement a son propre statut ; on journalise ceux que Langfuse a refusés.
            $body = $response->toArray(false);
            $status = $response->getStatusCode();
            if (($status >= 300 && $status !== 207) || ($body['errors'] ?? []) !== []) {
                $this->logger->warning(sprintf(
                    'Langfuse a refusé des événements (HTTP %d) : %s',
                    $status,
                    json_encode($body['errors'] ?? $body, \JSON_UNESCAPED_UNICODE),
                ));
            }
        } catch (HttpClientException $e) {
            $this->logger->warning(sprintf('Envoi des traces Langfuse impossible : %s', $e->getMessage()));
        }
    }

    public function reset(): void
    {
        $this->traceId = null;
        $this->events = [];
        $this->parents = [];
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function recordSpan(
        string $id,
        ?string $parent,
        string $name,
        mixed $input,
        mixed $output,
        string $start,
        array $metadata,
        ?string $level = null,
        ?string $statusMessage = null,
    ): void {
        $this->record('span-create', array_filter([
            'id' => $id,
            'traceId' => $this->traceId,
            'parentObservationId' => $parent,
            'name' => $name,
            'input' => $input,
            'output' => $output,
            'startTime' => $start,
            'endTime' => self::now(),
            'metadata' => $metadata === [] ? null : $metadata,
            'level' => $level,
            'statusMessage' => $statusMessage,
        ], static fn (mixed $value): bool => $value !== null));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function record(string $type, array $body): void
    {
        $this->events[] = ['id' => self::uuid(), 'type' => $type, 'timestamp' => self::now(), 'body' => $body];
    }

    public static function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z');
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = \chr(\ord($bytes[6]) & 0x0F | 0x40);
        $bytes[8] = \chr(\ord($bytes[8]) & 0x3F | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
