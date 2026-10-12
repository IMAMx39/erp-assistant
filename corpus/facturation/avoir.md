---
title: Créer un avoir
module: facturation
tags: [avoir, retour, remboursement, correction, AV]
---

# Avoirs

Un avoir annule tout ou partie d'une facture validée. Numérotation `AV-`. Rôle requis : Comptable.

## Créer un avoir

Depuis la facture concernée, bouton **Créer un avoir**, puis choisir :
- **Avoir total** : reprend l'intégralité de la facture.
- **Avoir partiel** : sélection des lignes et des quantités, ou montant libre avec motif.

Un avoir est toujours rattaché à une facture d'origine ; Gestix interdit qu'il dépasse le montant restant de cette facture.

## Retour de marchandise

Si l'avoir correspond à un retour physique, cocher **Réintégrer en stock** : un mouvement d'entrée est créé dans l'entrepôt choisi. Sans cette option, seule la partie comptable est corrigée.

## Imputation

L'avoir peut être déduit d'une facture non réglée du même client ou remboursé par virement.
