<?php

declare(strict_types=1);

namespace App\Erp\Entity;

use App\Erp\Enum\CustomerOrderStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'erp_customer_order')]
class CustomerOrder
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    /** @var Collection<int, CustomerOrderLine> */
    #[ORM\OneToMany(targetEntity: CustomerOrderLine::class, mappedBy: 'order', cascade: ['persist'])]
    private Collection $lines;

    public function __construct(
        /** CC- + numéro */
        #[ORM\Column(length: 12, unique: true)]
        private string $number,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        private Customer $customer,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        private Warehouse $warehouse,
        #[ORM\Column(length: 20, enumType: CustomerOrderStatus::class)]
        private CustomerOrderStatus $status,
        #[ORM\Column]
        private \DateTimeImmutable $orderedAt,
    ) {
        $this->lines = new ArrayCollection();
    }

    public function addLine(Article $article, int $quantity, string $unitPrice, int $reservedQuantity = 0, int $shippedQuantity = 0): self
    {
        $this->lines->add(new CustomerOrderLine($this, $article, $quantity, $unitPrice, $reservedQuantity, $shippedQuantity));

        return $this;
    }

    /**
     * Montant HT de la commande.
     */
    public function getTotalExcludingTax(): float
    {
        $total = 0.0;
        foreach ($this->lines as $line) {
            $total += $line->getQuantity() * (float) $line->getUnitPrice();
        }

        return round($total, 2);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function getWarehouse(): Warehouse
    {
        return $this->warehouse;
    }

    public function getStatus(): CustomerOrderStatus
    {
        return $this->status;
    }

    public function getOrderedAt(): \DateTimeImmutable
    {
        return $this->orderedAt;
    }

    /** @return Collection<int, CustomerOrderLine> */
    public function getLines(): Collection
    {
        return $this->lines;
    }
}
