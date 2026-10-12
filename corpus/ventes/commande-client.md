---
title: Commandes clients
module: ventes
tags: [commande client, réservation, encours, CC]
---

# Commandes clients

Menu **Ventes > Commandes clients**. Numérotation `CC-`.

## Validation et réservation

À la validation, Gestix réserve le stock disponible dans l'entrepôt de la commande. Si le stock est insuffisant, la ligne passe en **Reliquat** et sera réservée automatiquement dès qu'une réception le permettra.

## Contrôle de l'encours

Chaque client a un **plafond d'encours** (factures non réglées + commandes non facturées). Si la nouvelle commande dépasse ce plafond, elle est bloquée en statut **Bloquée encours** et doit être débloquée par un Comptable.

## Modification et annulation

Une commande peut être modifiée tant qu'aucune préparation n'a commencé. Son annulation libère le stock réservé.
