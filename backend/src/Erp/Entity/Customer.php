<?php

declare(strict_types=1);

namespace App\Erp\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'erp_customer')]
class Customer
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    public function __construct(
        /** CLI- + 5 chiffres */
        #[ORM\Column(length: 9, unique: true)]
        private readonly string  $code,
        #[ORM\Column(length: 100)]
        private readonly string  $name,
        /** Code pays ISO (FR, DE, CH…) */
        #[ORM\Column(length: 2)]
        private readonly string  $country,
        /** Plafond d'encours : factures non réglées + commandes non facturées (corpus : ventes/commande-client.md) */
        #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
        private readonly string  $creditLimit,
        /** Numéro de TVA intracommunautaire (clients professionnels de l'UE) */
        #[ORM\Column(length: 20, nullable: true)]
        private readonly ?string $vatNumber = null,
        /** Facturation « livraison » (une facture par BL) ou « mensuelle » */
        #[ORM\Column(length: 20)]
        private readonly string  $invoicingMode = 'livraison',
    )
    {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function getCreditLimit(): string
    {
        return $this->creditLimit;
    }

    public function getVatNumber(): ?string
    {
        return $this->vatNumber;
    }

    public function getInvoicingMode(): string
    {
        return $this->invoicingMode;
    }
}
