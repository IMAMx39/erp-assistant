<?php

declare(strict_types=1);

namespace App\Erp\Enum;

/**
 * Statut d'une quantité en stock : seul le stock « disponible » compte dans le stock physique utilisable.
 */
enum StockStatus: string
{
    case Available = 'disponible';
    case Dispute = 'litige';
    case InTransit = 'transit';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Disponible',
            self::Dispute => 'Litige',
            self::InTransit => 'En transit',
        };
    }
}
