.DEFAULT_GOAL := help

PHP_VERSION ?= 8.4
PHP_IMAGE   := php:$(PHP_VERSION)-cli
PHAN_IMAGE  := html-min-phan:$(PHP_VERSION)
DOCKER_RUN  := docker run --rm -v "$(CURDIR)":/app -w /app
PHP         := $(DOCKER_RUN) $(PHP_IMAGE) php
PHP_PHAN    := $(DOCKER_RUN) $(PHAN_IMAGE) php
COMPOSER    := $(DOCKER_RUN) composer:2 composer

.PHONY: help install update outdated test test-all phpstan phan phan-image cs cs-check rector rector-check quality ci clean

help: ## Show this help
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage: make \033[36m<target>\033[0m\n\nTargets:\n"} /^[a-zA-Z_-]+:.*?##/ { printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2 }' $(MAKEFILE_LIST)

install: ## Install composer dependencies
	$(COMPOSER) install --no-interaction --no-progress

update: ## Update all composer dependencies
	$(COMPOSER) update --no-interaction --no-progress

outdated: ## Show outdated composer dependencies
	$(COMPOSER) outdated --direct

test: ## Run phpunit on $(PHP_VERSION)
	$(PHP) vendor/bin/phpunit

test-all: ## Run phpunit on PHP 8.3, 8.4, 8.5
	@for v in 8.3 8.4 8.5; do \
		echo "=== PHP $$v ==="; \
		$(DOCKER_RUN) php:$$v-cli php vendor/bin/phpunit || exit 1; \
	done

phpstan: ## Run phpstan at level max
	$(PHP) vendor/bin/phpstan analyse --no-progress

phan-image: ## Build docker image with ext-ast for phan
	docker build --build-arg PHP_VERSION=$(PHP_VERSION) -t $(PHAN_IMAGE) -f docker/phan.Dockerfile docker

phan: phan-image ## Run phan static analyzer
	$(PHP_PHAN) vendor/bin/phan --no-progress-bar

cs: ## Fix code style
	$(PHP) vendor/bin/php-cs-fixer fix

cs-check: ## Check code style without modifying files
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff

rector: ## Apply rector refactors
	$(PHP) vendor/bin/rector process

rector-check: ## Preview rector refactors without modifying files
	$(PHP) vendor/bin/rector process --dry-run

quality: rector cs phpstan phan ## Run all code quality tools (rector → fixer → phpstan → phan)

ci: cs-check phpstan phan test-all ## Run the full CI pipeline locally

clean: ## Remove vendor and cache directories
	rm -rf vendor .phpstan.cache .phpunit.cache .php-cs-fixer.cache .phan/cache
