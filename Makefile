.PHONY: help up down build shell migrate seed test logs fresh pint stan lint-js format-js validate artisan composer npm dusk dusk-setup dusk-headed ssl-cert dev storage-link

EXEC = docker compose exec -u $(shell id -u):$(shell id -g) -e HOME=/tmp app

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

dev: ## run php artisan dev (server, queue listener, vite) inside the container
	@$(EXEC) php artisan dev

storage-link: ## create the public/storage symlink (needed for organisation logo uploads to display)
	@$(EXEC) php artisan storage:link

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

artisan: ## run any artisan command, e.g. make artisan cmd="make:seeder Foo"
	@$(EXEC) php artisan $(cmd)

composer: ## run any composer command, e.g. make composer cmd="require foo/bar"
	@$(EXEC) composer $(cmd)

npm: ## run any npm command, e.g. make npm cmd="install foo"
	@$(EXEC) npm $(cmd)

dusk-setup: ## migrate + seed the dedicated laravel_dusk database
	@$(EXEC) sh -c "DB_DATABASE=laravel_dusk php artisan migrate:fresh --seed --force"

dusk: ## run the dusk browser suite against the running app container
	@$(EXEC) php artisan dusk

dusk-headed: ## run dusk from the host with a visible browser window (needs host php, google-chrome, WSLg/X11)
	@DUSK_HEADLESS_DISABLED=true DUSK_CHROME_BINARY=/usr/bin/google-chrome php artisan dusk

ssl-cert: ## copy the local caddy CA root cert to ./storage/frankenphp-local-ca.crt for trusting on the host
	@docker compose cp app:/data/caddy/pki/authorities/local/root.crt storage/frankenphp-local-ca.crt
	@echo "Root cert saved to storage/frankenphp-local-ca.crt - see README for how to trust it."
