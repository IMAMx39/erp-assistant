<?php

declare(strict_types=1);

namespace App\Erp\Service;

use App\Erp\Entity\Article;
use App\Erp\Entity\CustomerOrderLine;
use App\Erp\Entity\StockLevel;
use App\Erp\Entity\Warehouse;
use App\Erp\Enum\CustomerOrderStatus;
use App\Erp\Enum\StockStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Calcul du stock selon stocks/mouvements-de-stock.md :
 * stock disponible = stock physique (statut disponible) − stock réservé par les commandes clients
 * validées non expédiées. Le stock en litige ou en transit n'est disponible nulle part.
 */
final class StockService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array<string, array{entrepot: string, stock_physique: int, stock_reserve: int, stock_disponible: int, en_litige: int, en_transit: int}>
     */
    public function byWarehouse(Article $article): array
    {
        $summary = [];
        foreach ($this->entityManager->getRepository(Warehouse::class)->findBy([], ['code' => 'ASC']) as $warehouse) {
            $summary[$warehouse->getCode()] = [
                'entrepot' => $warehouse->getCode(),
                'stock_physique' => 0,
                'stock_reserve' => 0,
                'stock_disponible' => 0,
                'en_litige' => 0,
                'en_transit' => 0,
            ];
        }

        foreach ($this->levels($article) as $level) {
            $code = $level->getLocation()->getWarehouse()->getCode();
            $key = match ($level->getStatus()) {
                StockStatus::Available => 'stock_physique',
                StockStatus::Dispute => 'en_litige',
                StockStatus::InTransit => 'en_transit',
            };
            $summary[$code][$key] += $level->getQuantity();
        }

        foreach ($this->reservedByWarehouse($article) as $code => $reserved) {
            $summary[$code]['stock_reserve'] = $reserved;
        }

        foreach ($summary as $code => $row) {
            $summary[$code]['stock_disponible'] = $row['stock_physique'] - $row['stock_reserve'];
        }

        return $summary;
    }

    public function available(Article $article, Warehouse $warehouse): int
    {
        return $this->byWarehouse($article)[$warehouse->getCode()]['stock_disponible'] ?? 0;
    }

    /**
     * @return list<StockLevel>
     */
    public function levels(Article $article): array
    {
        /** @var list<StockLevel> */
        return $this->entityManager->createQueryBuilder()
            ->select('s', 'l', 'w')
            ->from(StockLevel::class, 's')
            ->join('s.location', 'l')
            ->join('l.warehouse', 'w')
            ->where('s.article = :article')
            ->setParameter('article', $article)
            ->orderBy('w.code')
            ->addOrderBy('l.code')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<string, int> quantité réservée par code d'entrepôt
     */
    private function reservedByWarehouse(Article $article): array
    {
        /** @var list<array{code: string, reserved: int|string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('w.code AS code', 'SUM(line.reservedQuantity) AS reserved')
            ->from(CustomerOrderLine::class, 'line')
            ->join('line.order', 'o')
            ->join('o.warehouse', 'w')
            ->where('line.article = :article')
            ->andWhere('o.status IN (:statuses)')
            ->setParameter('article', $article)
            ->setParameter('statuses', array_filter(CustomerOrderStatus::cases(), static fn (CustomerOrderStatus $s): bool => $s->reservesStock()))
            ->groupBy('w.code')
            ->getQuery()
            ->getArrayResult();

        $reserved = [];
        foreach ($rows as $row) {
            $reserved[$row['code']] = (int) $row['reserved'];
        }

        return $reserved;
    }
}
