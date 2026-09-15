#!/bin/bash

DOCKER_CONTAINER = nurschool-php
HOST_OS := $(shell uname -s)

ifeq ($(filter Darwin Linux,$(HOST_OS)),$(HOST_OS))
	CURRENT_UID := $(shell id -u)
	CURRENT_GID := $(shell id -g)
	USER_ARG := --user $(CURRENT_UID):$(CURRENT_GID)
	ENV_VARS := HOST_UID=$(CURRENT_UID) HOST_GID=$(CURRENT_GID)
else
	CURRENT_UID = 1000
	USER_ARG := --user $(CURRENT_UID)
	ENV_VARS := HOST_UID=$(CURRENT_UID)
endif

help: # Shows this help message
	@echo 'usage: make [target]'
	@echo
	@echo 'targets:'
	@egrep '^(.+)\:\ ##\ (.+)' ${MAKEFILE_LIST} | column -t -c 2 -s ':#'

show-user-ids: ## Shows the user and group IDs sent to Docker Compose
	@echo "HOST_OS=$(HOST_OS)"
	@echo "CURRENT_UID=$(CURRENT_UID)"
	@echo "CURRENT_GID=$(CURRENT_GID)"

start: ## Starts the containers
# docker network create nurschool-network || true
	$(ENV_VARS) docker compose up -d

stop: ## Stops the containers
	docker compose stop

restart: ## Restarts the containers
	$(MAKE) stop && $(MAKE) run

build: ## Rebuilds all the containers
	 $(ENV_VARS) docker compose build

show: ## Shows the containers
	echo "=== CONTAINERS ==="
	$(ENV_VARS) docker compose ps -a

	echo "=== VOLUMES ==="
	$(ENV_VARS) docker volume ls

	echo "=== IMAGES ==="
	$(ENV_VARS) docker images -a

	echo "=== NETWORKS ==="
	$(ENV_VARS) docker network ls

# Commands
composer-install: ## Installs composer dependencies
	$(ENV_VARS) docker exec $(USER_ARG) -it ${DOCKER_CONTAINER} php -d xdebug.mode=off /usr/bin/composer install --no-scripts --optimize-autoloader

composer-update: ## Updates composer dependencies
	$(ENV_VARS) docker exec $(USER_ARG) -it ${DOCKER_CONTAINER} php -d xdebug.mode=off /usr/bin/composer update --no-scripts --optimize-autoloader

logs: ## Tails the Symfony dev log
	$(ENV_VARS) docker exec -it $(USER_ARG) ${DOCKER_CONTAINER} tail -f var/log/dev.log

bash: ## Opens a shell in the container
	$(ENV_VARS) docker exec -it $(USER_ARG) ${DOCKER_CONTAINER} sh

code-style-install: ## Installs php-cs-fixer
	$(ENV_VARS) docker exec -it $(USER_ARG) ${DOCKER_CONTAINER} makedir --parents tools/php-cs-fixer
	$(ENV_VARS) docker exec -it $(USER_ARG) ${DOCKER_CONTAINER} composer require --working-dir=tools/php-cs-fixer friendsofphp/php-cs-fixer

code-style: ## Runs php-cs to fix code styling follwing Symfony rules
	$(ENV_VARS) docker exec -it $(USER_ARG) ${DOCKER_CONTAINER} tools/php-cs-fixer/vendor/bin/php-cs-fixer fix src --rules=@Symfony

test: ## Runs tests with PHPUnit
	$(ENV_VARS) docker exec -it $(USER_ARG) ${DOCKER_CONTAINER} php -d xdebug.mode=off bin/phpunit
