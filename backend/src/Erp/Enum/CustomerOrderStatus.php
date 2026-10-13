<?php

declare(strict_types=1);

namespace App\Erp\Enum;

/**
 * Statut d'une commande client (corpus : ventes/commande-client.md, ventes/preparation-expedition.md).
 */
enum CustomerOrderStatus: string
{
    case Draft = 'brouillon';
    case Validated = 'validee';
    case CreditBlocked = 'bloquee_encours';
    case InPreparation = 'en_preparation';
    case Shipped = 'expediee';
    case Invoiced = 'facturee';
    case Cancelled = 'annulee';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Validated => 'Validée',
            self::CreditBlocked => 'Bloquée encours',
            self::InPreparation => 'En préparation',
            self::Shipped => 'Expédiée',
            self::Invoiced => 'Facturée',
            self::Cancelled => 'Annulée',
        };
    }

    /**
     * Commande validée et non expédiée : son stock est réservé (stock réservé du corpus).
     */
    public function reservesStock(): bool
    {
        return $this === self::Validated || $this === self::InPreparation;
    }

    /**
     * Commande non facturée qui compte dans l'encours du client.
     */
    public function countsInOutstanding(): bool
    {
        return $this === self::Validated || $this === self::CreditBlocked || $this === self::InPreparation || $this === self::Shipped;
    }
}
