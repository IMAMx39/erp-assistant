<?php

declare(strict_types=1);

namespace App\Erp\Entity;

use App\Erp\Enum\ArticleStatus;
use App\Erp\Repository\ArticleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[ORM\Table(name: 'erp_article')]
class Article
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    public function __construct(
        /** ART- + 5 chiffres */
        #[ORM\Column(length: 9, unique: true)]
        private readonly string        $code,
        #[ORM\Column(length: 80)]
        private readonly string        $label,
        #[ORM\Column(length: 50)]
        private readonly string        $family,
        /** Unité dans laquelle le stock est compté */
        #[ORM\Column(length: 20)]
        private readonly string        $stockUnit,
        #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
        private readonly string        $salePrice,
        #[ORM\ManyToOne]
        private readonly ?Supplier     $mainSupplier = null,
        /** Unité d'achat (ex. carton de 12) et nombre d'unités de stock qu'elle contient */
        #[ORM\Column(length: 20, nullable: true)]
        private readonly ?string       $purchaseUnit = null,
        #[ORM\Column]
        private readonly int           $conversionFactor = 1,
        #[ORM\Column(type: Types::DECIMAL, precision: 4, scale: 1)]
        private readonly string        $vatRate = '20.0',
        #[ORM\Column(length: 20, enumType: ArticleStatus::class)]
        private readonly ArticleStatus $status = ArticleStatus::Active,
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

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getFamily(): string
    {
        return $this->family;
    }

    public function getStockUnit(): string
    {
        return $this->stockUnit;
    }

    public function getSalePrice(): string
    {
        return $this->salePrice;
    }

    public function getMainSupplier(): ?Supplier
    {
        return $this->mainSupplier;
    }

    public function getPurchaseUnit(): ?string
    {
        return $this->purchaseUnit;
    }

    public function getConversionFactor(): int
    {
        return $this->conversionFactor;
    }

    public function getVatRate(): string
    {
        return $this->vatRate;
    }

    public function getStatus(): ArticleStatus
    {
        return $this->status;
    }
}
