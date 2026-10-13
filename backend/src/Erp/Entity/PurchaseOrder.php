<?php

declare(strict_types=1);

namespace App\Erp\Entity;

use App\Erp\Enum\PurchaseOrderStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'erp_purchase_order')]
class PurchaseOrder
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    /** @var Collection<int, PurchaseOrderLine> */
    #[ORM\OneToMany(targetEntity: PurchaseOrderLine::class, mappedBy: 'order', cascade: ['persist'])]
    private Collection $lines;

    public function __construct(
        /** CF- + numéro */
        #[ORM\Column(length: 12, unique: true)]
        private string $number,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        private Supplier $supplier,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        private Warehouse $warehouse,
        #[ORM\Column(length: 20, enumType: PurchaseOrderStatus::class)]
        private PurchaseOrderStatus $status,
        #[ORM\Column]
        private \DateTimeImmutable $orderedAt,
        #[ORM\Column(nullable: true)]
        private ?\DateTimeImmutable $expectedAt = null,
    ) {
        $this->lines = new ArrayCollection();
    }

    public function addLine(Article $article, int $quantity, string $unitPrice, int $receivedQuantity = 0): self
    {
        $this->lines->add(new PurchaseOrderLine($this, $article, $quantity, $unitPrice, $receivedQuantity));

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

    public function getSupplier(): Supplier
    {
        return $this->supplier;
    }

    public function getWarehouse(): Warehouse
    {
        return $this->warehouse;
    }

    public function getStatus(): PurchaseOrderStatus
    {
        return $this->status;
    }

    public function getOrderedAt(): \DateTimeImmutable
    {
        return $this->orderedAt;
    }

    public function getExpectedAt(): ?\DateTimeImmutable
    {
        return $this->expectedAt;
    }

    /** @return Collection<int, PurchaseOrderLine> */
    public function getLines(): Collection
    {
        return $this->lines;
    }
}
