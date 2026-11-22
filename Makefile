HOST ?= 127.0.0.1
PORT ?= 47321
URL  := http://$(HOST):$(PORT)/

.DEFAULT_GOAL := run
.PHONY: run open install config lint help

run: config ## Start the dev server (override with HOST=… PORT=…)
	@echo "Jira Hours → $(URL)"
	php -S $(HOST):$(PORT) -t public

open: ## Open the app in the default browser (macOS: open, Linux: xdg-open)
	@(command -v open >/dev/null && open $(URL)) || xdg-open $(URL)

install: ## Install Composer dependencies (rebuilds vendor/ and the autoloader)
	composer install

config: ## Create config.php from config.example.php if missing
	@if [ ! -f config.php ]; then \
		cp config.example.php config.php; \
		echo "Created config.php — edit it and set base_url and token."; \
	fi

lint: ## Syntax-check all PHP files
	@find public src -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null && echo "PHP syntax OK"

help: ## List targets
	@grep -E '^[a-z-]+:.*##' $(MAKEFILE_LIST) | sed -E 's/^([a-z-]+):.*## (.*)/  make \1\t\2/'
