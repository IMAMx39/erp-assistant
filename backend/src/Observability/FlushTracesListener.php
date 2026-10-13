<?php

declare(strict_types=1);

namespace App\Observability;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Envoie les traces une fois la réponse partie (kernel.terminate, après fastcgi_finish_request
 * sous PHP-FPM, et après la fin du flux pour une réponse streamée).
 */
final class FlushTracesListener
{
    public function __construct(
        private readonly LangfuseTracer $tracer,
    ) {
    }

    #[AsEventListener(KernelEvents::TERMINATE)]
    #[AsEventListener(ConsoleEvents::TERMINATE)]
    public function __invoke(): void
    {
        $this->tracer->flush();
    }
}
