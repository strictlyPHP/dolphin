# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Dolphin is a lightweight PHP framework (PHP >= 8.2) for running serverless functions on DigitalOcean. It builds on League Route and PHP-DI, adding attribute-based routing, automatic JSON-to-DTO mapping, and role-based access control. Published on Packagist as `strictlyphp/dolphpin`. See ARCHITECTURE.md for full internals.

## Commands

All Make targets build and run inside the Docker image defined in `Dockerfile` (no local PHP needed):

```bash
make install         # Install dependencies (removes vendor/ and composer.lock first)
make analyze         # PHPStan static analysis, level 6, on src and tests
make style           # Check coding style (ECS: PSR-12 + spaces/array/docblock/strict sets)
make style-fix       # Auto-fix coding style
make check-coverage  # Run all tests + verify coverage of files changed vs origin/main is >= 86%
make coveralls       # Run tests with coverage and upload to Coveralls
```

There is no plain `make test` target — `make check-coverage` is how CI runs the suite. To run tests directly (or a single test), run PHPUnit inside the Docker container:

```bash
docker build -t strictlyphp82/dolphin .
docker run --user=$(id -u):$(id -g) --rm -v "$PWD":/usr/src/myapp -w /usr/src/myapp strictlyphp82/dolphin \
  ./vendor/bin/phpunit tests/                                         # all tests
docker run --user=$(id -u):$(id -g) --rm -v "$PWD":/usr/src/myapp -w /usr/src/myapp strictlyphp82/dolphin \
  ./vendor/bin/phpunit tests/Unit/Strategy/DtoMapperTest.php          # one file
docker run --user=$(id -u):$(id -g) --rm -v "$PWD":/usr/src/myapp -w /usr/src/myapp strictlyphp82/dolphin \
  ./vendor/bin/phpunit --filter testMethodName tests/                 # one test
```

CI (`.github/workflows/pull_request.yml`) runs `make style`, `make analyze`, and `make check-coverage` on every PR — all three must pass.

## Architecture

Small source tree (`src/`), with the interesting logic concentrated in three places:

- **`App.php`** — `App::build()` is the static factory: creates the PHP-DI container, sets up Monolog, scans the given controller namespaces with ClassFinder, reads `#[Route]` attributes from discovered classes, and registers routes on a League Router using the custom strategy. `App::run()` converts a DigitalOcean function event/context pair into a PSR-7 request, dispatches it, and returns `['statusCode', 'body', 'headers']`.

- **`Strategy/DolphinAppStrategy.php`** — extends League Route's `JsonStrategy`. On each matched route it: checks `#[RequiresRoles]` against the `user` request attribute (`AuthenticatedUserInterface`, set by user middleware; 401 if absent, 403 if roles missing), resolves controller `__invoke` parameters via reflection (PSR-7 request, route vars array, or any class type mapped from the JSON body), and converts exceptions into structured JSON error responses (full details when debug mode is on).

- **`Strategy/DtoMapper.php`** — reflection-based JSON-to-object mapper. Handles scalars, value objects (single-constructor-arg classes), nested DTOs (recursive), backed enums (`::tryFrom()`), nullable params, and typed arrays whose element type comes from `@param array<Type>` docblocks. Array element class names are resolved by parsing the DTO source file's `use` statements with PHP-Parser (cached per class).

Tests are split into `tests/Unit` and `tests/Integration` (full `App::build()`/`run()` round-trips), with shared controllers/DTOs/middleware in `tests/Fixtures`.

## Release process

Releases are branch-driven (current branch pattern: `release/x.y.z`):

1. `./build/create-release.sh <version>` — creates `release/<version>`, regenerates `changelog.txt` from git log since the last tag, bumps `version` in `composer.json`, runs the full check suite, commits as `chore: prepare release <version>`, and pushes.
2. Merging that branch to `main` triggers `tag-and-release-on-merge-v1.yml`, which extracts the version from the merge commit message, tags it, and creates a GitHub release using `changelog.txt` as the body.

Do not hand-edit `changelog.txt` or the `version` field in `composer.json` outside this flow.
