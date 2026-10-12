include .env
export

.PHONY: init init-backend init-frontend up down logs sh db ollama-pull

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