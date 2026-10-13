<?php

declare(strict_types=1);

namespace App\Erp\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'erp_customer_order_line')]
class CustomerOrderLine
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'lines')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private CustomerOrder $order,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        private Article $article,
        #[ORM\Column]
        private int $quantity,
        #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
        private string $unitPrice,
        /** Quantité réservée à la validation ; le reste de la ligne est en reliquat */
        #[ORM\Column]
        private int $reservedQuantity = 0,
        #[ORM\Column]
        private int $shippedQuantity = 0,
    ) {
    }

    public function getBackorderQuantity(): int
    {
        return max(0, $this->quantity - $this->reservedQuantity - $this->shippedQuantity);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): CustomerOrder
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

    public function getReservedQuantity(): int
    {
        return $this->reservedQuantity;
    }

    public function getShippedQuantity(): int
    {
        return $this->shippedQuantity;
    }
}
