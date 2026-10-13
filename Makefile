include .env
export

.PHONY: init init-backend init-frontend up down logs sh db ollama-pull eval eval-retrieval fixtures

init: init-backend init-frontend

init-backend:
	mkdir -p backend corpus evals
	docker compose run --rm --no-deps php composer create-project symfony/skeleton:"7.4.*" .
	docker compose run --rm php composer require symfony/orm-pack symfony/messenger symfony/serializer-pack symfony/validator nelmio/cors-bundle
	docker compose run --rm php composer require --dev symfony/maker-bundle

init-frontend:
	docker run --rm -v "$(PWD)":/w -w /w node:22-alpine npx --yes create-next-app@latest frontend --ts --app --tailwind --eslint --src-dir --use-npm --yes

up:
	docker compose up -d --build

down:
	docker compose down

logs:
	docker compose logs -f

sh:
	docker compose exec php bash

db:
	docker compose exec mariadb mariadb -u$(DB_USER) -p$(DB_PASSWORD) $(DB_NAME)

ollama-pull:
	ollama pull nomic-embed-text

# Évaluation du RAG sur evals/dataset.yaml. promptfoo sort en erreur dès qu'un test échoue :
# le « - » laisse quand même produire la synthèse (evals/results/latest.md).
# Offre gratuite de Groq : 8 000 tokens/min et ~750 tokens par question, d'où une question toutes les 6 s.
# MODE : mode de recherche évalué (vector, hybrid, hybrid_rerank).
EVAL_DELAY ?= 6000
MODE ?= hybrid_rerank
eval:
	@rm -f evals/results/latest.json evals/results/latest.html
	-docker compose --profile evals run --rm evals eval -c promptfooconfig.yaml --no-cache -j 1 --delay $(EVAL_DELAY) --var mode=$(MODE) -o results/latest.json -o results/latest.html
	docker compose --profile evals run --rm --no-deps --entrypoint node evals summarize.mjs results/latest.json > evals/results/latest.md
	@cat evals/results/latest.md

# Recherche seule, par section, sans génération (questions hors corpus exclues).
# Sans délai pour vector et hybrid ; hybrid_rerank appelle Groq : RETRIEVAL_DELAY=12000.
# LIMIT : nombre d'extraits demandés (5 comme le chat ; 10 = vivier soumis au reclassement).
RETRIEVAL_DELAY ?= 0
LIMIT ?= 5
eval-retrieval:
	@rm -f evals/results/retrieval-$(MODE)-$(LIMIT).json
	-docker compose --profile evals run --rm evals eval -c retrieval.yaml --no-cache -j 1 --delay $(RETRIEVAL_DELAY) --var mode=$(MODE) --var limit=$(LIMIT) --filter-pattern '^(?!refus-|agent-)' -o results/retrieval-$(MODE)-$(LIMIT).json
	docker compose --profile evals run --rm --no-deps --entrypoint node evals summarize-retrieval.mjs results/retrieval-$(MODE)-$(LIMIT).json > evals/results/retrieval-$(MODE)-$(LIMIT).md
	@cat evals/results/retrieval-$(MODE)-$(LIMIT).md

# Données de l'ERP fictif. Sans exclusions, doctrine:fixtures:load viderait aussi l'index documentaire.
fixtures:
	docker compose exec php php bin/console doctrine:fixtures:load -n \
		--purge-exclusions=document --purge-exclusions=chunk \
		--purge-exclusions=messenger_messages --purge-exclusions=doctrine_migration_versions
