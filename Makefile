# Lone Wolf — developer convenience wrappers around docker compose.

COMPOSE ?= docker compose
PHP_SERVICE := php

.PHONY: up down logs test lint console npm ps restart db-migrate

up: ## Boot the whole stack
	$(COMPOSE) up --build -d
	@echo "Backend:  http://localhost:8080"
	@echo "Frontend: http://localhost:3000"

down: ## Stop the stack
	$(COMPOSE) down

logs: ## Tail all service logs
	$(COMPOSE) logs -f --tail=100

ps: ## Show service status
	$(COMPOSE) ps

restart: ## Restart the stack
	$(COMPOSE) restart

db-migrate: ## Run Doctrine migrations
	$(COMPOSE) exec $(PHP_SERVICE) bin/console doctrine:migrations:migrate -n

test: ## Run backend test suites + frontend Vitest
	$(COMPOSE) exec $(PHP_SERVICE) vendor/bin/phpunit --testsuite unit
	$(COMPOSE) exec $(PHP_SERVICE) vendor/bin/phpunit --testsuite integration
	$(COMPOSE) exec $(PHP_SERVICE) vendor/bin/behat
	$(COMPOSE) exec frontend npm run test

lint: ## Static quality gates (PHPStan + deptrac)
	$(COMPOSE) exec $(PHP_SERVICE) composer lint

console: ## Shell into the php container
	$(COMPOSE) exec $(PHP_SERVICE) sh

npm: ## Run an npm command in the frontend container: make npm CMD="install"
	$(COMPOSE) exec frontend npm $(CMD)
