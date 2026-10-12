---
title: Créer un article
module: articles
tags: [article, fiche article, création]
---

# Créer un article

Menu **Articles > Nouvel article**. Rôles autorisés : Administrateur, Responsable logistique.

## Champs obligatoires

- **Code article** : généré automatiquement au format `ART-` suivi de 5 chiffres. Il ne peut plus être modifié après le premier mouvement de stock.
- **Libellé** : 80 caractères maximum.
- **Famille** : voir [Familles et unités](familles-et-unites.md).
- **Unité de stock** : unité dans laquelle le stock est compté.
- **Taux de TVA** : 20 % par défaut.

## Champs facultatifs

Prix d'achat de référence, prix de vente HT, poids, fournisseur principal, codes-barres, stock minimum.

## Statuts d'un article

- **Actif** : utilisable dans toutes les pièces.
- **Bloqué à la vente** : visible en stock mais impossible à ajouter sur un devis ou une commande client.
- **Archivé** : uniquement possible si le stock est à zéro dans tous les entrepôts et qu'aucune commande n'est en cours.

Un article archivé peut être réactivé par un Administrateur.
