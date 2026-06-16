.PHONY: help setup up down restart build logs shell artisan composer migrate fresh seed tinker cache-clear db

GREEN  = \033[0;32m
YELLOW = \033[0;33m
NC     = \033[0m

help:
	@echo ""
	@echo "  $(GREEN)Finance App$(NC)"
	@echo ""
	@awk 'BEGIN {FS = ":.*##"} /^[a-zA-Z_-]+:.*##/ { printf "  $(YELLOW)%-16s$(NC) %s\n", $$1, $$2 }' $(MAKEFILE_LIST)
	@echo ""

setup: ## Configura o projeto do zero (rode uma vez)
	@[ -f .env ] || cp .env.example .env
	docker compose up -d --build
	@echo "$(GREEN)▶ Aguardando banco...$(NC)" && sleep 5
	docker compose exec php composer install --no-interaction
	docker compose exec php php artisan key:generate
	docker compose exec php php artisan migrate --seed
	@echo "$(GREEN)✔ Acesse: http://localhost:$${APP_PORT:-8080}$(NC)"

up: ## Sobe os containers
	docker compose up -d

down: ## Para os containers
	docker compose down

restart: ## Reinicia os containers
	docker compose restart

build: ## Reconstrói a imagem PHP
	docker compose build --no-cache php

logs: ## Logs de todos os serviços
	docker compose logs -f

logs-php: ## Logs do PHP-FPM
	docker compose logs -f php

shell: ## Shell no container PHP
	docker compose exec -u app php bash

db: ## psql no PostgreSQL
	docker compose exec postgres psql -U $${DB_USERNAME:-finance} -d $${DB_DATABASE:-finance}

artisan: ## Ex: make artisan CMD="route:list"
	docker compose exec -u app php php artisan $(CMD)

migrate: ## Roda migrations
	docker compose exec -u app php php artisan migrate

fresh: ## Recria banco do zero
	docker compose exec -u app php php artisan migrate:fresh --seed

seed: ## Apenas seeders
	docker compose exec -u app php php artisan db:seed

tinker: ## REPL Laravel
	docker compose exec -u app php php artisan tinker

cache-clear: ## Limpa caches
	docker compose exec -u app php php artisan optimize:clear

composer: ## Ex: make composer CMD="require pacote/x"
	docker compose exec -u app php composer $(CMD)
