include .env
export

.PHONY: init init-backend init-frontend up down logs sh db ollama-pull eval

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
EVAL_DELAY ?= 6000
eval:
	-docker compose --profile evals run --rm evals eval -c promptfooconfig.yaml --no-cache -j 1 --delay $(EVAL_DELAY) -o results/latest.json -o results/latest.html
	docker compose --profile evals run --rm --no-deps --entrypoint node evals summarize.mjs results/latest.json > evals/results/latest.md
	@cat evals/results/latest.md
