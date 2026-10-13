<?php

declare(strict_types=1);

namespace App\Erp\Entity;

use App\Erp\Enum\StockStatus;
use Doctrine\ORM\Mapping as ORM;

/**
 * Quantité physique d'un article sur un emplacement, par statut (disponible, litige, en transit).
 */
#[ORM\Entity]
#[ORM\Table(name: 'erp_stock_level')]
class StockLevel
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        private Article $article,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        private Location $location,
        #[ORM\Column]
        private int $quantity,
        #[ORM\Column(length: 20, enumType: StockStatus::class)]
        private StockStatus $status = StockStatus::Available,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getArticle(): Article
    {
        return $this->article;
    }

    public function getLocation(): Location
    {
        return $this->location;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getStatus(): StockStatus
    {
        return $this->status;
    }
}
