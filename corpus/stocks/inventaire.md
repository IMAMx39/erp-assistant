---
title: Réaliser et clôturer un inventaire
module: stocks
tags: [inventaire, comptage, écart, clôture]
---

# Réaliser et clôturer un inventaire

Menu **Stocks > Inventaires > Nouvel inventaire**. Chaque inventaire reçoit un numéro `INV-`.

## Types d'inventaire

- **Complet** : tout un entrepôt. Obligatoire au moins une fois par an.
- **Tournant** : une sélection d'emplacements ou de familles.

## Déroulement

1. **Ouverture** : les emplacements concernés sont gelés ; aucun mouvement ne peut y être saisi.
2. **Comptage** : saisie sur terminal ou sur feuille de comptage imprimée.
3. **Contrôle des écarts** : Gestix compare quantités comptées et théoriques. Un écart de plus de 10 % sur une ligne déclenche un recomptage obligatoire.
4. **Clôture** : les écarts sont transformés en mouvements d'ajustement et les emplacements sont dégelés.

## Conditions de clôture

La clôture est impossible tant qu'il reste :
- des lignes non comptées ;
- des recomptages demandés et non effectués ;
- des réceptions ou expéditions en attente sur les emplacements concernés.

Seul un Responsable logistique peut clôturer. Un inventaire clôturé ne peut pas être rouvert.
