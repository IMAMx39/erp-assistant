# CLAUDE.md — erp-assistant

## Objectif

Projet portfolio : un **assistant documentaire IA** pour un ERP fictif nommé **Gestix**.
Il répond aux questions des utilisateurs à partir de la documentation (RAG, avec citation des sources),
interroge des données ERP en lecture seule via des outils (agent), expose ces outils via un serveur MCP,
et sa qualité est mesurée par une suite d'évaluation chiffrée.

Le projet doit rester présentable à un recruteur : code propre, commits réguliers, README avec résultats mesurés.

**Interdit :** utiliser du code, de la documentation ou des données réelles d'une entreprise. Tout est fictif.

## Stack et environnement

- Mac avec **OrbStack** (pas Docker Desktop). Projet monté depuis `/Volumes/kawimdev/Project/erp-assistant`.
- Docker Compose : `php` (PHP 8.2-FPM), `worker` (Messenger), `nginx`, `mariadb` (11.8, support vectoriel natif), `frontend` (Node 22, Next.js).
- **PHP 8.2** : toute dépendance doit être compatible. Ne jamais installer un paquet qui exige PHP 8.4 (ex. composants Symfony 8).
- Backend : **Symfony 7.4**, Doctrine ORM (attributs), Messenger (transport Doctrine).
- Frontend : Next.js (App Router, TypeScript, Tailwind).
- **Ollama tourne en natif sur le Mac** (GPU Metal), joignable depuis les conteneurs via `http://host.docker.internal:11434`.
  Modèle d'embedding : `nomic-embed-text` (768 dimensions).
- Génération des réponses : API Anthropic, petit modèle (Claude Haiku) pour limiter les coûts.
- Stockage vectoriel : bundle Symfony `imamx95/mariadb-vector-bundle` (projet de l'auteur, à valoriser).
- Ports : API `localhost:8080`, front `localhost:3000`, MariaDB `localhost:3306`.

## Commandes

```bash
make up                                   # démarre la stack (build inclus)
docker compose up -d --force-recreate     # à utiliser si un conteneur ne voit pas les bons fichiers
make sh                                   # shell dans le conteneur php
make db                                   # client MariaDB
docker compose exec php php bin/console <commande>
docker compose restart worker             # OBLIGATOIRE après toute modification de code traité par Messenger
docker compose logs -f worker
docker compose exec php php bin/console app:corpus:ingest [--force]
```

Toujours préfixer par `php` : `php bin/console`, jamais `bin/console` seul.

## Architecture backend (par domaine fonctionnel)

```
backend/src/
├── Entity/          # Document, Chunk (+ entités vectorielles)
├── Repository/
├── Ingestion/       # Parser/, Chunker/, Dto/, Message/, MessageHandler/, Command/
├── Retrieval/       # recherche vectorielle, hybride (FULLTEXT + vecteurs, RRF), reranking
├── Chat/            # contrôleur, PromptBuilder, streaming
├── Agent/Tool/      # outils de consultation de l'ERP fictif
├── Erp/Entity/      # ERP fictif : Article, Entrepot, Emplacement, Stock, Commande…
└── Mcp/             # serveur MCP
```

Autres dossiers : `corpus/` (documentation Markdown), `evals/` (promptfoo), `frontend/`, `docker/`.

## Corpus

- 19 pages Markdown dans `corpus/`, avec front matter `title`, `module`, `tags`.
- `corpus/README.md` définit l'univers de référence (entrepôts `ORL` et `LYO`, codes `ART-xxxxx`, `CF-`, `CC-`, `FA-`, `AV-`, `INV-`, rôles).
  **Les données de l'ERP fictif doivent respecter cet univers**, pour que documentation et données soient cohérentes.
- Ne pas modifier le corpus sans demande explicite : les questions d'évaluation s'appuient sur ses règles précises.

## Conventions de code

- `declare(strict_types=1);` dans chaque fichier PHP.
- Attributs PHP 8 partout (Doctrine, routes, commandes, `#[AsMessageHandler]`, `#[Autowire]`).
- Promotion de propriétés dans les constructeurs, `readonly` pour les DTO et messages, classes `final` par défaut (sauf entités et repositories).
- Typage complet, annotations PHPDoc pour les tableaux (`list<string>`, `Collection<int, Chunk>`).
- Messages utilisateur, logs et descriptions de commandes **en français**. Code et noms de classes en anglais.
- Configuration par variables d'environnement (`CORPUS_DIR`, `OLLAMA_URL`, `ANTHROPIC_API_KEY`). Aucun secret en dur.

## Règles de travail

1. **Changements minimaux** sur le code existant. Respecter les noms et conventions déjà en place.
2. Avant une modification importante ou l'ajout d'une dépendance, **proposer un plan et attendre validation**.
3. Signaler brièvement tout point ambigu ou risqué avant d'agir (suppression de données, migration destructive…).
4. Après chaque étape : générer la migration si besoin, l'exécuter, redémarrer le worker, puis **vérifier le résultat** (commande, requête SQL, logs).
5. Commits au format Conventional Commits, en français : `feat: …`, `fix: …`, `docs: …`, `chore: …`.
6. Ne jamais toucher aux fichiers `.env` contenant des secrets ; documenter les nouvelles variables dans `.env.example`.

## Planning (congé de deux semaines)

- [x] **J1** — Stack Docker, dépôt Git, corpus Gestix.
- [ ] **J2** — Ingestion : entités `Document`/`Chunk`, parser front matter, chunker par `##`, commande `app:corpus:ingest`, handler Messenger idempotent (hash SHA-256). *Code écrit, migration à générer et à tester.*
- [ ] **J3** — Embeddings via Ollama, stockage avec le bundle MariaDB vector, endpoint de recherche testable.
- [ ] **J4** — Génération (Symfony AI ou client HTTP Anthropic), citation des sources, refus si l'information est absente, streaming (en-tête `X-Accel-Buffering: no` pour nginx).
- [ ] **J5** — Interface de chat Next.js avec affichage des sources. → **v1 présentable**.
- [ ] **J6** — Jeu d'évaluation (~50 questions/réponses attendues), promptfoo, mesure de référence. **Prioritaire : ne jamais sacrifier.**
- [ ] **J7** — Recherche hybride (FULLTEXT MariaDB + vecteurs, fusion RRF) et reranking, puis nouvelle mesure.
- [ ] **J8** — ERP fictif (entités + fixtures cohérentes avec le corpus), outils de l'agent, tool calling.
- [ ] **J9** — Serveur MCP (testé dans Claude Desktop), traces Langfuse (compose séparé). Sacrifiable si retard.
- [ ] **J10** — CI GitHub Actions avec évaluation automatique, déploiement sur le serveur AWS, README (schéma d'architecture + résultats chiffrés).

Hors périmètre pour l'instant : multi-tenant.

## Pièges connus

- Fichiers téléchargés renommés (ex. `default.conf` devenu `default`) : Docker crée alors un **dossier** à la place du fichier monté. Vérifier les noms exacts.
- Si un dossier monté est remplacé pendant que les conteneurs tournent, ils restent attachés à l'ancien : `docker compose up -d --force-recreate`.
- Le worker ne recharge pas le code : `docker compose restart worker`.
- `app:corpus:ingest` supprime en base les documents absents du corpus ; un garde-fou bloque l'opération si aucun fichier n'est trouvé.
- Doctrine insère avant de supprimer lors d'un flush : ne pas ajouter de contrainte unique sur `(document_id, position)` dans `chunk`.