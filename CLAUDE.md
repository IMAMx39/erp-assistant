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
  Modèle d'embedding : `nomic-embed-text` (768 dimensions), variable `OLLAMA_EMBED_MODEL`.
- Génération des réponses, fournisseur choisi par `LLM_PROVIDER` (`anthropic` par défaut) :
  - `anthropic` : SDK PHP officiel `anthropic-ai/sdk`, modèle `claude-haiku-5-5` (`ANTHROPIC_MODEL`), citations natives de l'API ;
  - `groq` (gratuit, modèles open source), `mistral`, `ollama` (local) : une même classe `ChatCompletionsAnswerGenerator` (API compatible OpenAI via `symfony/http-client`), une instance par fournisseur dans `services.yaml` ; citations par marqueurs `[n]` analysés dans le flux. Variables : `GROQ_API_KEY`/`GROQ_MODEL` (par défaut `qwen/qwen3.8-27b`), `MISTRAL_API_KEY`/`MISTRAL_MODEL`, `OLLAMA_CHAT_MODEL`.
- Stockage vectoriel : bundle Symfony `inm39/mariadb-vector-bundle` (projet de l'auteur, à valoriser ; dépôt `IMAMx39/mariadb-vector-bundle`, copie locale dans `../mariadb-vector-bundle`).
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
├── Entity/          # Document, Chunk (colonne embedding VECTOR(768))
├── Repository/
├── Doctrine/Type/   # type DBAL « vector » (correctif temporaire du bundle)
├── Embedding/       # EmbedderInterface, OllamaEmbedder
├── Ingestion/       # Parser/, Chunker/, Dto/, Message/, MessageHandler/, Command/
├── Retrieval/       # VectorRetriever, GET /api/search ; à venir : hybride (FULLTEXT + vecteurs, RRF), reranking
├── Chat/            # ChatService (RAG), PromptBuilder, Generator/ (Anthropic, Chat Completions), POST /api/chat (JSON) et /api/chat/stream (SSE)
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
- [x] **J2** — Ingestion : entités `Document`/`Chunk`, parser front matter, chunker par `##`, commande `app:corpus:ingest`, handler Messenger idempotent (hash SHA-256).
- [x] **J3** — Embeddings via Ollama, stockage avec le bundle MariaDB vector, endpoint de recherche testable (`GET /api/search?q=…&limit=5`).
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
- Le type `vector` du bundle v1.0.1 écrit en binaire, ce que MariaDB rejette via PDO (« Incorrect vector value ») : `App\Doctrine\Type\VectorType` le remplace (écriture JSON + `VEC_FromText`). À retirer quand le bundle sera corrigé.
- Le `VECTOR INDEX` s'ajoute à la main dans la migration ; il est déclaré comme index simple sur l'entité (c'est ainsi que DBAL le relit) et `mapping_types: vector` est requis dans `doctrine.yaml`.
- L'embedding est `NOT NULL` : il est calculé dans le handler avant le flush. Si Ollama ne répond pas, le message part dans la file `failed` après 3 essais (`php bin/console messenger:failed:retry`).
- Après la recréation du conteneur `php`, nginx garde l'ancienne IP et renvoie 502 : `docker compose restart nginx`.
- Les générateurs Chat Completions sont déclarés avec `autoconfigure: false` : sinon l'autoconfiguration leur ajoute une seconde étiquette sans `key` et `LLM_PROVIDER` ne les trouve plus.
