<?php

declare(strict_types=1);

namespace App\Chat\Controller;

use Anthropic\Core\Exceptions\AnthropicException;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Chat\ChatService;
use App\Chat\Dto\ChatRequest;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;

final class ChatController extends AbstractController
{
    public function __construct(
        private readonly ChatService $chatService,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/api/chat', name: 'api_chat', methods: ['POST'])]
    public function ask(#[MapRequestPayload] ChatRequest $request): JsonResponse
    {
        try {
            return $this->json($this->chatService->ask($request->question));
        } catch (AnthropicException|HttpClientException $e) {
            [$status, $message] = $this->failure($e);

            return $this->json(['error' => $message], $status);
        }
    }

    /**
     * Réponse en Server-Sent Events : sources, delta, citation, done (ou error).
     */
    #[Route('/api/chat/stream', name: 'api_chat_stream', methods: ['POST'])]
    public function askStream(#[MapRequestPayload] ChatRequest $request): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($request): void {
            try {
                foreach ($this->chatService->stream($request->question) as $event) {
                    $this->sendEvent($event->type, $event->data);
                }
            } catch (AnthropicException|HttpClientException $e) {
                $this->sendEvent('error', ['message' => $this->failure($e)[1]]);
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream; charset=utf-8');
        $response->headers->set('Cache-Control', 'no-cache');
        // Sans cet en-tête, nginx met la réponse en tampon et le flux arrive d'un bloc.
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    private function sendEvent(string $type, mixed $data): void
    {
        echo 'event: '.$type."\n";
        echo 'data: '.json_encode($data, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR)."\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    /**
     * @return array{int, string} code HTTP et message affichable
     */
    private function failure(AnthropicException|HttpClientException $e): array
    {
        $this->logger->error('Échec de la génération de réponse', ['exception' => $e]);

        return match (true) {
            $e instanceof HttpClientException => [503, 'La recherche dans la documentation est indisponible (Ollama injoignable).'],
            $e instanceof AuthenticationException => [500, 'Clé API Anthropic absente ou invalide (ANTHROPIC_API_KEY).'],
            $e instanceof RateLimitException => [503, 'Trop de demandes en cours, réessayez dans quelques instants.'],
            $e instanceof APIConnectionException => [503, 'Le service de génération est injoignable.'],
            default => [502, 'Le service de génération a renvoyé une erreur.'],
        };
    }
}
