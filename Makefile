.PHONY: help up down build shell migrate seed test logs fresh

help: ## show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "%-10s %s\n", $$1, $$2}'

up: ## docker compose up -d
	@docker compose up -d

down: ## docker compose down
	@docker compose down

build: ## docker compose build
	@docker compose build

shell: ## exec into the app container
	@docker compose exec app sh

migrate: ## run artisan migrate
	@docker compose exec app php artisan migrate

seed: ## run artisan db:seed
	@docker compose exec app php artisan db:seed

test: ## run php artisan test
	@docker compose exec app php artisan test

logs: ## tail app container logs
	@docker compose logs -f app

fresh: ## migrate:fresh --seed
	@docker compose exec app php artisan migrate:fresh --seed
