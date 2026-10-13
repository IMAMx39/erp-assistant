# Recherche hybride et reclassement (J7)

Même jeu de questions que la mesure de référence ([baseline.md](baseline.md)) : 46 questions couvertes par le corpus,
chacune avec la ou les sections (chunks) qui contiennent la réponse. Mesures produites par `make eval-retrieval`.

## Recherche par section

| Mode | Section attendue parmi les 5 extraits | MRR | Parmi 10 extraits | Latence médiane |
|---|---|---|---|---|
| `vector` : vecteurs seuls (référence J6) | 93 % (43/46) | 0,82 | 100 % | 19 ms |
| `hybrid` : vecteurs + plein texte MariaDB, fusion RRF | 91 % (42/46) | 0,84 | 100 % | 24 ms |
| `hybrid_rerank` : hybride, puis reclassement des 10 meilleurs par LLM | **100 % (46/46)** | **0,99** | — | 381 ms |

Par catégorie, en `hybrid_rerank` : faits précis 100 % (MRR 1,00), plusieurs pages 100 % (0,94), reformulations 100 % (1,00),
contre 67 % (0,56) pour les reformulations en recherche vectorielle seule.

## Enseignements

- **La recherche plein texte seule n'améliore rien sur ce corpus.** Sans racinisation, « bloqué » ne rejoint pas « verrouillé »
  ni « livré » « reçue » ; et la liste de mots vides d'InnoDB est anglaise (les mots vides français sont retirés de la question
  dans `FulltextRetriever`). À poids égal dans la fusion RRF, elle déplace autant de bonnes sections qu'elle en ramène.
- **Le problème n'était pas de trouver mais de classer** : avec 10 extraits, les deux modes retrouvent 100 % des sections attendues.
  Le reclassement par LLM, appliqué à ces 10 candidats, place la bonne section en tête dans 45 cas sur 46.
- Les trois échecs de la mesure de référence sont récupérés côté recherche, y compris « un mois et demi » → relances à J+45,
  qu'aucune recherche lexicale ou vectorielle ne rapprochait.
- Coût : environ 1 000 à 1 300 tokens de plus par question et ~350 ms de latence. En cas d'échec du reclassement (quota),
  l'ordre de la recherche hybride est conservé : la question ne tombe jamais en erreur.

## Évaluation complète (réponses), mode `hybrid_rerank`

**Incomplète** : le quota gratuit de Groq (200 000 tokens par jour et par modèle) a été épuisé après 26 questions sur 54.
Ces 26 questions (25 faits précis, 1 reformulation) réussissent tous les contrôles : recherche, exactitude, citations, absence de refus à tort.
La mesure complète, comparable à la référence (94 % de réussite), reste à relancer une fois le quota reconstitué :

```bash
make eval MODE=hybrid_rerank EVAL_DELAY=18000
```
