---
title: Mouvements de stock
module: stocks
tags: [mouvement, entrée, sortie, ajustement, stock disponible]
---

# Mouvements de stock

Chaque variation de stock est tracée par un mouvement : date, utilisateur, article, emplacement, quantité et pièce d'origine.

## Types de mouvements

- **Entrée** : réception fournisseur, retour client.
- **Sortie** : expédition client, mise au rebut.
- **Transfert** : entre emplacements ou entrepôts.
- **Ajustement** : correction manuelle ou écart d'inventaire. Un motif est obligatoire.

## Stock physique et stock disponible

- **Stock physique** : quantité réellement présente.
- **Stock réservé** : quantité affectée à des commandes clients validées mais non expédiées.
- **Stock disponible** = stock physique − stock réservé.

Un mouvement validé ne peut pas être supprimé. Pour le corriger, il faut saisir un mouvement inverse.

Les ajustements supérieurs à 500 € de valeur nécessitent la validation d'un Responsable logistique.
