# Mesure de référence (J6)

Recherche vectorielle seule (nomic-embed-text, 5 extraits), génération Groq. Point de comparaison du J7 (recherche hybride et reranking), avec le même jeu de questions (`evals/dataset.yaml`).

- Date : 2026-10-13T09:23:54.740Z
- Modèle : qwen/qwen3.8-27b
- Questions : 54 (46 couvertes par le corpus, 8 hors corpus)

## Vue d'ensemble

| Mesure | Résultat | Sur |
|---|---|---|
| Recherche : une source attendue parmi les 5 extraits | 98 % (45/46) | questions couvertes |
| Exactitude : tous les faits attendus dans la réponse | 93 % (43/46) | questions couvertes |
| Citations : au moins une, toutes vers une source acceptée | 93 % (43/46) | questions couvertes |
| Refus justes | 100 % (8/8) | questions hors corpus |
| Refus à tort | 7 % (3/46) | questions couvertes |
| Réussite complète (tous les contrôles) | 94 % (51/54) | toutes |

Latence : médiane 269 ms, 95e centile 508 ms. Tokens moyens par question : 669 en entrée, 32 en sortie.

## Par catégorie

| Catégorie | Questions | Recherche | Exactitude | Citations | Refus correct |
|---|---|---|---|---|---|
| Fait précis (une page) | 32 | 100 % (32/32) | 100 % (32/32) | 100 % (32/32) | 100 % (32/32) |
| Plusieurs pages | 8 | 100 % (8/8) | 88 % (7/8) | 88 % (7/8) | 88 % (7/8) |
| Reformulation | 6 | 83 % (5/6) | 67 % (4/6) | 67 % (4/6) | 67 % (4/6) |
| Hors corpus (refus attendu) | 8 | — | — | — | 100 % (8/8) |

## Échecs

- **multi-devis-vers-facture** (multi) — refus : Refus à tort ; exactitude : Faits manquants : commande, bon de livraison ; citations : Aucune citation
  > Je ne trouve pas cette information dans la documentation Gestix.
- **para-livraison-excedentaire** (paraphrase) — refus : Refus à tort ; exactitude : Faits manquants : 5 %, responsable logistique ; citations : Aucune citation
  > Je ne trouve pas cette information dans la documentation Gestix.
- **para-client-mauvais-payeur** (paraphrase) — recherche : Sources retrouvées : facturation/facture-client.md, ventes/devis.md, faq.md ; refus : Refus à tort ; exactitude : Faits manquants : mise en demeure ; citations : Aucune citation
  > Je ne trouve pas cette information dans la documentation Gestix.

## Analyse

Les trois échecs viennent de la **recherche**, pas de la génération : faute d'extrait pertinent, le modèle refuse, ce qui est le comportement attendu (aucune réponse inventée, 100 % de refus justes hors corpus).

| Question | Ce qui manque parmi les 5 extraits |
|---|---|
| multi-devis-vers-facture | La bonne page est retrouvée, mais pas les sections clés : « Factures clients › Génération » (factures issues des bons de livraison) et « Préparation et expédition › Expédition ». |
| para-livraison-excedentaire | `achats/reception.md` est retrouvé, mais pas la section « Écarts et litiges » (tolérance de 5 %). |
| para-client-mauvais-payeur | « un mois et demi » ne rapproche pas de « J+45 » : `facturation/relances.md` est absent des extraits. |

Limite de la mesure : la recherche est évaluée **par page** (98 %), alors que deux des trois échecs sont des sections manquantes dans une page retrouvée. Les pistes du J7 visent précisément ce point : recherche plein texte (FULLTEXT) combinée aux vecteurs, fusion RRF, puis reranking des extraits.
