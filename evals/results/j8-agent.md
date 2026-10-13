# Agent et outils de l'ERP fictif (J8)

15 questions sur les données de l'ERP (`agent-*` dans `evals/dataset.yaml`), dont les valeurs attendues viennent des
fixtures (`src/DataFixtures/ErpFixtures.php`). Deux contrôles par question : au moins un des outils attendus est appelé,
et les valeurs attendues figurent dans la réponse.

Modèle : `openai/gpt-oss-120b` via Groq (le quota journalier de `qwen/qwen3.8-27b` était épuisé), recherche `vector`.

## Résultats

| Mesure | Première mesure | Après corrections |
|---|---|---|
| Bon outil appelé | 87 % (13/15) | 100 % (14/14) |
| Valeurs attendues dans la réponse | 53 % (8/15) | 100 % (14/14) |
| Erreurs d'appel | 0 (une réponse vide passée inaperçue) | 1, corrigée puis vérifiée isolément |

Coût moyen : ~3 600 tokens en entrée et ~330 en sortie par question (définitions des 7 outils incluses) ; latence médiane 1,7 s.

## Ce que la première mesure a révélé

| Échec | Cause | Correction |
|---|---|---|
| 3 réponses justes comptées fausses | Codes écrits avec un tiret insécable (`ART‑30205`) | Normalisation des tirets Unicode dans `assertions.cjs` |
| Stock à Lyon : 30 au lieu de 25 ; stock réservé : refus | Le modèle utilisait `get_stock` (quantités physiques par emplacement, sans réservations) | Descriptions des outils précisées, remarque dans la sortie de `get_stock` renvoyant vers `get_article` |
| Code article des gants : refus | Recherche de « gants » dans la famille Manutention (ils sont en EPI) : zéro résultat | `search_articles` élargit à toutes les familles si le filtre ne donne rien, et le signale |
| Réponse vide | Erreur envoyée par Groq dans le flux (HTTP 200), ignorée | Erreur de flux remontée et journalisée avec son détail |
| Erreur de flux (2e mesure) | Paramètre facultatif envoyé à `null`, refusé par la validation de schéma de Groq | Paramètres facultatifs déclarés `["string", "null"]` |
| « 144 cartons » au lieu de 144 paires | Quantité en unités de stock affichée à côté de l'unité d'achat | Sortie explicite : `144 paires, soit 12 × carton de 12` |

Leçon : la qualité d'un agent dépend autant de la conception des outils (descriptions, tolérance aux arguments imparfaits,
sorties sans ambiguïté) que du modèle.

## Exemple

> **Pourquoi la commande CC-24060 est-elle bloquée ?**
>
> Outil appelé : `get_customer_order {"number": "CC-24060"}`
>
> La commande CC-24060 est en statut Bloquée encours parce que l'encours du client (5 609 €) dépasse son plafond
> d'encours (5 000 €) ; elle doit être débloquée par un Comptable [1].

La réponse combine les données de l'ERP (encours, plafond) et la documentation citée (qui peut débloquer).

## À refaire

Mesure complète avec le modèle par défaut (`qwen/qwen3.8-27b`) une fois son quota reconstitué :

```bash
make eval MODE=hybrid_rerank EVAL_DELAY=30000
```
