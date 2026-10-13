<?php

declare(strict_types=1);

namespace App\Chat\Controller;

use App\Chat\ChatService;
use App\Chat\Dto\ChatRequest;
use App\Chat\Exception\GenerationFailedException;
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
        private readonly ChatService     $chatService,
        private readonly LoggerInterface $logger,
    )
    {
    }

    #[Route('/api/chat', name: 'api_chat', methods: ['POST'])]
    public function ask(#[MapRequestPayload] ChatRequest $request): JsonResponse
    {
        try {
            return $this->json($this->chatService->ask($request->question, $request->retrievalMode()));
        } catch (GenerationFailedException|HttpClientException $e) {
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
                foreach ($this->chatService->stream($request->question, $request->retrievalMode()) as $event) {
                    $this->sendEvent($event->type, $event->data);
                }
            } catch (GenerationFailedException|HttpClientException $e) {
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
        echo 'event: ' . $type . "\n";
        echo 'data: ' . json_encode($data, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR) . "\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    /**
     * @return array{int, string} code HTTP et message affichable
     */
    private function failure(GenerationFailedException|HttpClientException $e): array
    {
        $this->logger->error('Échec de la génération de réponse', ['exception' => $e]);

        if ($e instanceof GenerationFailedException) {
            return [$e->statusCode, $e->getMessage()];
        }

        return [503, 'La recherche dans la documentation est indisponible (Ollama injoignable).'];
    }
}
