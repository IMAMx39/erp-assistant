---
title: Réapprovisionnement et stock minimum
module: stocks
tags: [réapprovisionnement, stock minimum, suggestion, rupture]
---

# Réapprovisionnement

## Stock minimum

Le **stock minimum** se définit par article et par entrepôt. Lorsque le stock disponible passe en dessous, l'article apparaît dans **Stocks > Suggestions de réapprovisionnement**.

## Calcul de la quantité suggérée

Quantité suggérée = stock maximum − (stock disponible + quantités en commande fournisseur non reçues).

Si aucun stock maximum n'est défini, Gestix propose le double du stock minimum. La quantité est arrondie au multiple supérieur de l'unité d'achat.

## Générer les commandes

Depuis les suggestions, le bouton **Générer les commandes** crée une commande fournisseur `CF-` en brouillon par fournisseur principal. Les articles sans fournisseur principal restent dans la liste et doivent être traités manuellement.
