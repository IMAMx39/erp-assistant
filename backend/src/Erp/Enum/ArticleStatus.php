<?php

declare(strict_types=1);

namespace App\Erp\Enum;

/**
 * Statut d'un article (corpus : articles/creer-un-article.md).
 */
enum ArticleStatus: string
{
    case Active = 'actif';
    case BlockedForSale = 'bloque_vente';
    case Archived = 'archive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Actif',
            self::BlockedForSale => 'Bloqué à la vente',
            self::Archived => 'Archivé',
        };
    }
}
