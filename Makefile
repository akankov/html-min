.DEFAULT_GOAL := help

PHP_VERSION ?= 8.4
PHP_IMAGE   := php:$(PHP_VERSION)-cli
PHAN_IMAGE  := html-min-phan:$(PHP_VERSION)
DOCKER_RUN  := docker run --rm -v "$(CURDIR)":/app -w /app
PHP         := $(DOCKER_RUN) $(PHP_IMAGE) php
PHP_PHAN    := $(DOCKER_RUN) $(PHAN_IMAGE) php
COMPOSER    := $(DOCKER_RUN) composer:2 composer

.PHONY: help install update outdated test test-all phpstan phan phan-image cs cs-check rector rector-check bench-install bench bench-quick bench-baseline bench-cs bench-cs-check bench-rector bench-rector-check bench-phpstan bench-phan bench-quality quality ci clean

BENCH_PHP      := docker run --rm -v "$(CURDIR)":/app -w /app/benchmarks -e BENCH_GIT_SHA=$(shell git rev-parse --short HEAD 2>/dev/null || echo unknown) $(PHP_IMAGE) php
BENCH_PHP_PHAN := docker run --rm -v "$(CURDIR)":/app -w /app/benchmarks $(PHAN_IMAGE) php
BENCH_COMPOSER := docker run --rm -v "$(CURDIR)":/app -w /app/benchmarks composer:2 composer

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

bench-install: ## Install composer dependencies for benchmarks
	$(BENCH_COMPOSER) install --no-interaction --no-progress

bench: ## Run full benchmark suite and write docs/benchmarks/latest.md
	mkdir -p docs/benchmarks benchmarks/build
	$(BENCH_PHP) vendor/bin/phpbench run src/Bench/MinifyBench.php --dump-file=build/bench.xml
	$(BENCH_PHP) bin/compression-report.php > benchmarks/build/compression.json
	$(BENCH_PHP) bin/render-report.php build/bench.xml build/compression.json ../docs/benchmarks/latest.md

bench-quick: ## Faster bench for local loops (fewer iterations)
	mkdir -p docs/benchmarks benchmarks/build
	$(BENCH_PHP) vendor/bin/phpbench run src/Bench/MinifyBench.php --iterations=2 --revs=3 --warmup=1 --dump-file=build/bench.xml
	$(BENCH_PHP) bin/compression-report.php > benchmarks/build/compression.json
	$(BENCH_PHP) bin/render-report.php build/bench.xml build/compression.json ../docs/benchmarks/latest.md

bench-baseline: ## Copy docs/benchmarks/latest.md to baseline.md for release-diff purposes
	cp docs/benchmarks/latest.md docs/benchmarks/baseline.md

bench-cs: ## Fix code style in benchmarks
	$(PHP) vendor/bin/php-cs-fixer fix

bench-cs-check: ## Check benchmarks code style without modifying
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff

bench-rector: ## Apply rector refactors to benchmarks
	$(BENCH_PHP) vendor/bin/rector process

bench-rector-check: ## Preview rector refactors for benchmarks
	$(BENCH_PHP) vendor/bin/rector process --dry-run

bench-phpstan: ## Run phpstan on benchmarks
	$(BENCH_PHP) vendor/bin/phpstan analyse --no-progress --memory-limit=512M

bench-phan: phan-image ## Run phan on benchmarks
	$(BENCH_PHP_PHAN) vendor/bin/phan --no-progress-bar

bench-quality: bench-rector bench-cs bench-phpstan bench-phan ## Run all quality tools on benchmarks

quality: rector cs phpstan phan ## Run all library code quality tools (rector → fixer → phpstan → phan)

ci: cs-check phpstan phan test-all ## Run the full CI pipeline locally

clean: ## Remove vendor and cache directories
	rm -rf vendor .phpstan.cache .phpunit.cache .php-cs-fixer.cache .phan/cache
	rm -rf benchmarks/vendor benchmarks/.phpbench.cache benchmarks/.phpunit.cache benchmarks/build
