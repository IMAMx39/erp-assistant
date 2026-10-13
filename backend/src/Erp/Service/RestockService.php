<?php

declare(strict_types=1);

namespace App\Erp\Service;

use App\Erp\Entity\PurchaseOrderLine;
use App\Erp\Entity\StockRule;
use App\Erp\Entity\Warehouse;
use App\Erp\Enum\PurchaseOrderStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Suggestions de réapprovisionnement (stocks/reapprovisionnement.md) :
 * un article apparaît quand son stock disponible passe sous le minimum ;
 * quantité suggérée = stock maximum − (stock disponible + quantités en commande fournisseur non reçues),
 * arrondie au multiple supérieur de l'unité d'achat. Sans stock maximum, la cible est le double du minimum.
 */
final class RestockService
{
    private const INCOMING_STATUSES = [PurchaseOrderStatus::Validated, PurchaseOrderStatus::Sent, PurchaseOrderStatus::PartiallyReceived];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StockService           $stockService,
    )
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function suggestions(?Warehouse $warehouse = null): array
    {
        $criteria = $warehouse === null ? [] : ['warehouse' => $warehouse];
        $suggestions = [];

        foreach ($this->entityManager->getRepository(StockRule::class)->findBy($criteria) as $rule) {
            $article = $rule->getArticle();
            $available = $this->stockService->available($article, $rule->getWarehouse());
            if ($available >= $rule->getMinimum()) {
                continue;
            }

            $incoming = $this->incoming($rule);
            $target = $rule->getMaximum() ?? 2 * $rule->getMinimum();
            $quantity = max(0, $target - ($available + $incoming));
            $factor = max(1, $article->getConversionFactor());
            $quantity = (int)(ceil($quantity / $factor) * $factor);
            $supplier = $article->getMainSupplier();

            $suggestions[] = [
                'article' => $article->getCode(),
                'libelle' => $article->getLabel(),
                'entrepot' => $rule->getWarehouse()->getCode(),
                'stock_disponible' => $available,
                'stock_minimum' => $rule->getMinimum(),
                'stock_maximum' => $rule->getMaximum(),
                'en_commande_non_recue' => $incoming,
                'quantite_suggeree' => $quantity,
                'unite_achat' => $article->getPurchaseUnit() ?? $article->getStockUnit(),
                'fournisseur_principal' => $supplier?->getCode(),
                'remarque' => $supplier === null
                    ? 'Aucun fournisseur principal : à traiter manuellement'
                    : ($quantity === 0 ? 'Quantités déjà en commande suffisantes' : null),
            ];
        }

        usort($suggestions, static fn(array $a, array $b): int => [$a['entrepot'], $a['article']] <=> [$b['entrepot'], $b['article']]);

        return $suggestions;
    }

    private function incoming(StockRule $rule): int
    {
        $lines = $this->entityManager->createQueryBuilder()
            ->select('line', 'o')
            ->from(PurchaseOrderLine::class, 'line')
            ->join('line.order', 'o')
            ->where('line.article = :article')
            ->andWhere('o.warehouse = :warehouse')
            ->andWhere('o.status IN (:statuses)')
            ->setParameter('article', $rule->getArticle())
            ->setParameter('warehouse', $rule->getWarehouse())
            ->setParameter('statuses', self::INCOMING_STATUSES)
            ->getQuery()
            ->getResult();

        return array_sum(array_map(static fn(PurchaseOrderLine $line): int => $line->getRemainingQuantity(), $lines));
    }
}
