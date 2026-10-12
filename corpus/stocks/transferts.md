---
title: Transferts entre emplacements et entrepôts
module: stocks
tags: [transfert, rangement, inter-entrepôt]
---

# Transferts

## Transfert interne

Déplacement entre deux emplacements d'un même entrepôt. Immédiat : la sortie et l'entrée sont enregistrées en une seule opération. Utilisé notamment pour ranger la marchandise du quai vers son emplacement définitif.

## Transfert inter-entrepôts

Déplacement entre `ORL` et `LYO`. Il se fait en deux temps :

1. **Expédition** depuis l'entrepôt d'origine : le stock passe en statut **En transit**.
2. **Réception** dans l'entrepôt de destination : le stock devient disponible.

Le stock en transit n'est disponible dans aucun des deux entrepôts. Un transfert en transit depuis plus de 7 jours apparaît en alerte sur le tableau de bord logistique.
