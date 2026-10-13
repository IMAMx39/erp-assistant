<?php

declare(strict_types=1);

namespace App\Erp\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'erp_supplier')]
class Supplier
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    public function __construct(
        /** FRN- + 4 chiffres */
        #[ORM\Column(length: 8, unique: true)]
        private string $code,
        #[ORM\Column(length: 100)]
        private string $name,
        #[ORM\Column(length: 150)]
        private string $purchasingEmail,
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

    public function getPurchasingEmail(): string
    {
        return $this->purchasingEmail;
    }
}
