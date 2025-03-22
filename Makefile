.PHONY: help
help: ## Display this help message
	@cat $(MAKEFILE_LIST) | grep -e "^[a-zA-Z_\-]*: *.*## *" | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

#################
### COMMANDS ####
#################

.PHONY: analyze
analyze: ## Runs static analysis tools
		 docker build -t strictlyphp80/dolphin . && docker run --user=$(shell id -u):$(shell id -g) --rm --name strictlyphp80-dolphin -v "${PWD}":/usr/src/myapp -w /usr/src/myapp strictlyphp80/dolphin php ./vendor/bin/phpstan analyse -l 6 -c phpstan.neon src

.PHONY: test-coverage
test-coverage: ## Run phpunit tests with coverage
		docker build -t strictlyphp80/dolphin . && docker run --user=$(shell id -u):$(shell id -g) --rm --name strictlyphp80-dolphin -v "${PWD}":/usr/src/myapp -w /usr/src/myapp -e XDEBUG_MODE=coverage strictlyphp80/dolphin ./vendor/bin/phpunit tests/ --coverage-php=/tmp/coverage.cov --coverage-html=/tmp/coverage.html

.PHONY: check-coverage
check-coverage: ## Check the test coverage of changed files
		git fetch origin && git diff origin/main > ${PWD}/diff.txt && docker build -t strictlyphp80/dolphin . && docker run --user=$(shell id -u):$(shell id -g) --rm --name strictlyphp80-dolphin -v "${PWD}":/usr/src/myapp -w /usr/src/myapp strictlyphp80/dolphin ./build/check-coverage.sh

.PHONY: install
install: ## Install dependencies
		 docker build -t strictlyphp80/dolphin . && docker run --user=$(shell id -u):$(shell id -g) --rm --name strictlyphp80-dolphin -v "${PWD}":/usr/src/myapp -w /usr/src/myapp strictlyphp80/dolphin composer install

.PHONY: style
style: ## Check coding style
		 docker build -t strictlyphp80/dolphin . && docker run --user=$(shell id -u):$(shell id -g) --rm --name strictlyphp80-dolphin -v "${PWD}":/usr/src/myapp -w /usr/src/myapp strictlyphp80/dolphin php ./vendor/bin/ecs

.PHONY: style-fix
style-fix: ## Check coding style
		 docker build -t strictlyphp80/dolphin . && docker run --user=$(shell id -u):$(shell id -g) --rm --name strictlyphp80-dolphin -v "${PWD}":/usr/src/myapp -w /usr/src/myapp strictlyphp80/dolphin php ./vendor/bin/ecs --fix