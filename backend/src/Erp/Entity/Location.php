<?php

declare(strict_types=1);

namespace App\Erp\Entity;

use App\Erp\Enum\LocationType;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'erp_location')]
#[ORM\UniqueConstraint(name: 'uniq_location_code', columns: ['warehouse_id', 'code'])]
class Location
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        private readonly Warehouse    $warehouse,
        /** Format ALLEE-RAYON-NIVEAU, ex. A-03-2 ; « QUAI » pour la zone de réception */
        #[ORM\Column(length: 20)]
        private readonly string       $code,
        #[ORM\Column(length: 20, enumType: LocationType::class)]
        private readonly LocationType $type = LocationType::Storage,
        /** Gelé pendant un inventaire : aucun mouvement possible */
        #[ORM\Column]
        private readonly bool         $frozen = false,
    )
    {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWarehouse(): Warehouse
    {
        return $this->warehouse;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getType(): LocationType
    {
        return $this->type;
    }

    public function isFrozen(): bool
    {
        return $this->frozen;
    }
}
