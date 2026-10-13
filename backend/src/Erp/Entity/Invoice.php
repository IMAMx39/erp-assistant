<?php

declare(strict_types=1);

namespace App\Erp\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'erp_invoice')]
class Invoice
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    public function __construct(
        /** FA- + numéro chronologique */
        #[ORM\Column(length: 12, unique: true)]
        private readonly string             $number,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        private readonly Customer           $customer,
        #[ORM\Column]
        private readonly \DateTimeImmutable $issuedAt,
        #[ORM\Column]
        private readonly \DateTimeImmutable $dueAt,
        #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
        private readonly string    $amountIncludingTax,
        #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
        private readonly string             $paidAmount = '0.00',
        /** 0 à 3 (corpus : facturation/relances.md) ; 3 = mise en demeure et blocage des commandes */
        #[ORM\Column]
        private readonly int                $reminderLevel = 0,
        #[ORM\ManyToOne]
        private readonly ?CustomerOrder     $customerOrder = null,
    ) {
    }

    public function getOutstandingAmount(): float
    {
        return round((float) $this->amountIncludingTax - (float) $this->paidAmount, 2);
    }

    public function isOverdue(\DateTimeImmutable $today): bool
    {
        return $this->getOutstandingAmount() > 0 && $this->dueAt < $today;
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

    public function getIssuedAt(): \DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function getDueAt(): \DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function getAmountIncludingTax(): string
    {
        return $this->amountIncludingTax;
    }

    public function getPaidAmount(): string
    {
        return $this->paidAmount;
    }

    public function getReminderLevel(): int
    {
        return $this->reminderLevel;
    }

    public function getCustomerOrder(): ?CustomerOrder
    {
        return $this->customerOrder;
    }
}
