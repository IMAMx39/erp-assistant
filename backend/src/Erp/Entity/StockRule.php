<?php

declare(strict_types=1);

namespace App\Erp\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Stock minimum (et maximum facultatif) d'un article dans un entrepôt
 * (corpus : stocks/reapprovisionnement.md).
 */
#[ORM\Entity]
#[ORM\Table(name: 'erp_stock_rule')]
#[ORM\UniqueConstraint(name: 'uniq_stock_rule', columns: ['article_id', 'warehouse_id'])]
class StockRule
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        private readonly Article   $article,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        private readonly Warehouse $warehouse,
        #[ORM\Column]
        private readonly int       $minimum,
        #[ORM\Column(nullable: true)]
        private readonly ?int      $maximum = null,
    )
    {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getArticle(): Article
    {
        return $this->article;
    }

    public function getWarehouse(): Warehouse
    {
        return $this->warehouse;
    }

    public function getMinimum(): int
    {
        return $this->minimum;
    }

    public function getMaximum(): ?int
    {
        return $this->maximum;
    }
}
