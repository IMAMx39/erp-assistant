<?php

declare(strict_types=1);

namespace App\Agent\Tool;

use App\Erp\Entity\Warehouse;
use App\Erp\Service\RestockService;
use Doctrine\ORM\EntityManagerInterface;

final class RestockSuggestionsTool implements AgentToolInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RestockService $restockService,
    ) {
    }

    public function name(): string
    {
        return 'restock_suggestions';
    }

    public function description(): string
    {
        return 'Articles dont le stock disponible est passé sous le stock minimum, avec la quantité de réapprovisionnement '
            .'suggérée (stock maximum − (disponible + commandé non reçu), arrondie à l\'unité d\'achat) et le fournisseur principal. '
            .'Filtrable par entrepôt (ORL ou LYO).';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'warehouse' => ['type' => 'string', 'enum' => ['ORL', 'LYO'], 'description' => 'Entrepôt (facultatif : tous par défaut)'],
            ],
        ];
    }

    public function execute(array $arguments): array
    {
        $warehouse = null;
        if (\is_string($arguments['warehouse'] ?? null) && $arguments['warehouse'] !== '') {
            $warehouse = $this->entityManager->getRepository(Warehouse::class)->findOneBy(['code' => strtoupper($arguments['warehouse'])]);
            if ($warehouse === null) {
                return ['erreur' => 'Entrepôt inconnu : utilisez ORL ou LYO.'];
            }
        }

        $suggestions = $this->restockService->suggestions($warehouse);

        return ['nombre' => \count($suggestions), 'suggestions' => $suggestions];
    }
}
