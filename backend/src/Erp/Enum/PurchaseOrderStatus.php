<?php

declare(strict_types=1);

namespace App\Erp\Enum;

/**
 * Statut d'une commande fournisseur (corpus : achats/commande-fournisseur.md).
 */
enum PurchaseOrderStatus: string
{
    case Draft = 'brouillon';
    case Validated = 'validee';
    case Sent = 'envoyee';
    case PartiallyReceived = 'partiellement_recue';
    case Closed = 'soldee';
    case Cancelled = 'annulee';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Validated => 'Validée',
            self::Sent => 'Envoyée',
            self::PartiallyReceived => 'Partiellement reçue',
            self::Closed => 'Soldée',
            self::Cancelled => 'Annulée',
        };
    }
}
