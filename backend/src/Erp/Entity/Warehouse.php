<?php

declare(strict_types=1);

namespace App\Erp\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'erp_warehouse')]
class Warehouse
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    public function __construct(
        /** ORL ou LYO */
        #[ORM\Column(length: 3, unique: true)]
        private string $code,
        #[ORM\Column(length: 100)]
        private string $name,
    ) {
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
}
