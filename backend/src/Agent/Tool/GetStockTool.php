<?php

declare(strict_types=1);

namespace App\Agent\Tool;

use App\Erp\Service\StockService;

final class GetStockTool implements AgentToolInterface
{
    public function __construct(
        private readonly ArticleLookup $articles,
        private readonly StockService $stockService,
    ) {
    }

    public function name(): string
    {
        return 'get_stock';
    }

    public function description(): string
    {
        return "Détail du stock d'un article emplacement par emplacement (ex. A-03-2, QUAI), avec le statut de chaque quantité "
            .'(disponible, litige, en transit) et les emplacements gelés par un inventaire. Filtrable par entrepôt (ORL ou LYO).';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'code' => ['type' => 'string', 'description' => "Code de l'article, ex. ART-10452"],
                'warehouse' => ['type' => 'string', 'enum' => ['ORL', 'LYO'], 'description' => 'Entrepôt (facultatif)'],
            ],
            'required' => ['code'],
        ];
    }

    public function execute(array $arguments): array
    {
        $article = $this->articles->find($arguments['code'] ?? null);
        if ($article === null) {
            return ['erreur' => sprintf('Aucun article avec le code « %s ».', (string) ($arguments['code'] ?? ''))];
        }

        $warehouse = \is_string($arguments['warehouse'] ?? null) ? strtoupper($arguments['warehouse']) : null;
        $locations = [];
        foreach ($this->stockService->levels($article) as $level) {
            $location = $level->getLocation();
            if ($warehouse !== null && $location->getWarehouse()->getCode() !== $warehouse) {
                continue;
            }
            $locations[] = [
                'entrepot' => $location->getWarehouse()->getCode(),
                'emplacement' => $location->getCode(),
                'type_emplacement' => $location->getType()->label(),
                'quantite' => $level->getQuantity(),
                'statut' => $level->getStatus()->label(),
                'emplacement_gele' => $location->isFrozen(),
            ];
        }

        return [
            'article' => $article->getCode(),
            'libelle' => $article->getLabel(),
            'unite_stock' => $article->getStockUnit(),
            'emplacements' => $locations,
        ];
    }
}
