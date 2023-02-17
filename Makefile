#!/bin/bash

DOCKER_CONTAINER = nurschool-php
OS := $(shell uname)

ifeq ($(OS),Darwin)
	UID = $(shell id -u)
else ifeq ($(OS),Linux)
	UID = $(shell id -u)
else
	UID = 1000
endif

help: # Shows this help message
	@echo 'usage: make [target]'
	@echo
	@echo 'targets:'
	@egrep '^(.+)\:\ ##\ (.+)' ${MAKEFILE_LIST} | column -t -c 2 -s ':#'

start: ## Starts the containers
	U_ID=${UID} docker-compose up -d

stop: ## Stops the containers
	U_ID=${UID} docker-compose stop

restart: ## Restarts the containers
	$(MAKE) stop && $(MAKE) run

build: ## Rebuilds all the containers
	U_ID=${UID} docker-compose build

# Commands
composer-install: ## Installs composer dependencies
	U_ID=${UID} docker exec --user ${UID} -it ${DOCKER_CONTAINER} php -d xdebug.mode=off /usr/bin/composer install --no-scripts --no-interaction --optimize-autoloader

composer-update: ## Updates composer dependencies
	U_ID=${UID} docker exec --user ${UID} -it ${DOCKER_CONTAINER} php -d xdebug.mode=off /usr/bin/composer update --no-scripts --no-interaction --optimize-autoloader

logs: ## Tails the Symfony dev log
	U_ID=${UID} docker exec -it --user ${UID} ${DOCKER_CONTAINER} tail -f var/log/dev.log

bash: ## bash into the container
	U_ID=${UID} docker exec -it --user ${UID} ${DOCKER_CONTAINER} bash

code-style-install: ## Installs php-cs-fixer
	U_ID=${UID} docker exec -it --user ${UID} ${DOCKER_CONTAINER} makedir --parents tools/php-cs-fixer
	U_ID=${UID} docker exec -it --user ${UID} ${DOCKER_CONTAINER} composer require --working-dir=tools/php-cs-fixer friendsofphp/php-cs-fixer

code-style: ## Runs php-cs to fix code styling follwing Symfony rules
	U_ID=${UID} docker exec -it --user ${UID} ${DOCKER_CONTAINER} tools/php-cs-fixer/vendor/bin/php-cs-fixer fix src --rules=@Symfony

test: ## Runs tests with PHPUnit
	U_ID=${UID} docker exec -it --user ${UID} ${DOCKER_CONTAINER} php -d xdebug.mode=off bin/phpunit
