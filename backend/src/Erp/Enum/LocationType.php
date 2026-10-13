<?php

declare(strict_types=1);

namespace App\Erp\Enum;

/**
 * Type d'emplacement (corpus : stocks/entrepots-et-emplacements.md).
 */
enum LocationType: string
{
    case Storage = 'stockage';
    case Dock = 'quai';

    public function label(): string
    {
        return match ($this) {
            self::Storage => 'Stockage',
            self::Dock => 'Quai',
        };
    }
}
