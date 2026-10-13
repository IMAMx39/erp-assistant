<?php

declare(strict_types=1);

namespace App\Erp\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'erp_purchase_order_line')]
class PurchaseOrderLine
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'lines')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private readonly PurchaseOrder $order,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        private readonly Article       $article,
        /** Quantité commandée, en unités de stock */
        #[ORM\Column]
        private readonly int           $quantity,
        #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
        private readonly string        $unitPrice,
        #[ORM\Column]
        private readonly int           $receivedQuantity = 0,
    )
    {
    }

    /**
     * Reliquat : quantité commandée restant à recevoir.
     */
    public function getRemainingQuantity(): int
    {
        return max(0, $this->quantity - $this->receivedQuantity);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): PurchaseOrder
    {
        return $this->order;
    }

    public function getArticle(): Article
    {
        return $this->article;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getUnitPrice(): string
    {
        return $this->unitPrice;
    }

    public function getReceivedQuantity(): int
    {
        return $this->receivedQuantity;
    }
}
