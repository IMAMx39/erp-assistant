---
title: TVA et taxes
module: parametrage
tags: [TVA, taxe, autoliquidation, export]
---

# TVA et taxes

Menu **Paramétrage > Taxes**.

Taux disponibles : 20 % (normal, par défaut), 10 %, 5,5 % et 0 %.

## Règles automatiques selon le client

- **Client en France** : taux de l'article.
- **Client professionnel dans l'UE** avec numéro de TVA intracommunautaire valide : facturation HT avec mention d'autoliquidation.
- **Client hors UE** : facturation HT, mention d'exonération pour export.

Si le numéro de TVA intracommunautaire est absent, Gestix applique la TVA française et affiche un avertissement sur la fiche client.

Un changement de taux ne s'applique qu'aux pièces créées après la modification.
