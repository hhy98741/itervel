.PHONY: help up down restart build shell composer npm test test-parallel playwright

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

## Docker commands

build: ## Build containers
	docker compose build

up: ## Start all containers
	docker compose up -d

down: ## Stop all containers
	docker compose down

shell: ## Access app container shell
	docker compose exec app bash

logs: ## View container logs
	docker compose logs -f app

## Applicaton development commands

ai: ## Install/Update Laravel Boost
	php artisan boost:install

setup: ## Initial setup
	composer setup

dependencies: ## Install compose and npm dependencies
	composer install
	npm install

dev: ## Start dev server
	composer run dev

seed: ## Run database seeders
	php artisan db:seed

fresh: ## Fresh database with seeders
	php artisan migrate:fresh --seed

migrate: ## Run database migrations
	php artisan migrate

rebuild: ## Drop database and run database migrations
	php artisan migrate:fresh

format: ## Run lint and formatting
	composer lint
	npm run lint:fix
	npm run format

test: ## Run Laravel tests
	php artisan test --parallel --coverage --profile