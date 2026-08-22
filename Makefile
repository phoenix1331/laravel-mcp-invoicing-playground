.PHONY: help up down build shell migrate seed test logs fresh pint stan lint-js format-js validate

EXEC = docker compose exec -u $(shell id -u):$(shell id -g) app

help: ## show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "%-10s %s\n", $$1, $$2}'

up: ## docker compose up -d
	@docker compose up -d

down: ## docker compose down
	@docker compose down

build: ## docker compose build
	@docker compose build

shell: ## exec into the app container
	@$(EXEC) sh

migrate: ## run artisan migrate
	@$(EXEC) php artisan migrate

seed: ## run artisan db:seed
	@$(EXEC) php artisan db:seed

test: ## run pest
	@$(EXEC) ./vendor/bin/pest

logs: ## tail app container logs
	@docker compose logs -f app

fresh: ## migrate:fresh --seed
	@$(EXEC) php artisan migrate:fresh --seed

pint: ## run pint --test
	@$(EXEC) ./vendor/bin/pint --test

stan: ## run larastan level 8
	@$(EXEC) ./vendor/bin/phpstan analyse --memory-limit=512M

lint-js: ## check js/css formatting with prettier
	@$(EXEC) npm run lint

format-js: ## fix js/css formatting with prettier
	@$(EXEC) npm run format

validate: ## composer validate --strict
	@$(EXEC) composer validate --strict
