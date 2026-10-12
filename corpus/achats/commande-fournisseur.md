---
title: Commandes fournisseurs
module: achats
tags: [achat, commande fournisseur, validation, CF]
---

# Commandes fournisseurs

Menu **Achats > Commandes fournisseurs**. Numérotation `CF-`.

## Statuts

**Brouillon** → **Validée** → **Envoyée** → **Partiellement reçue** → **Soldée**. Une commande peut être **Annulée** tant qu'aucune réception n'a eu lieu.

## Circuit de validation

- Montant HT inférieur à 5 000 € : validation par le Responsable logistique.
- Montant HT de 5 000 € ou plus : validation supplémentaire par un Administrateur.

Une commande validée ne peut plus être modifiée. Pour la changer, il faut la repasser en brouillon (si elle n'a pas été envoyée) ou l'annuler et en créer une nouvelle.

## Envoi

Le bouton **Envoyer** génère le PDF et l'envoie par e-mail au contact achats du fournisseur.
