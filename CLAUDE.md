# CLAUDE.md

Guidance for coding agents working in this repository.

## Project

`mongodb/laravel-mongodb` is the official Laravel integration for MongoDB. It provides an Eloquent model, query builder, schema builder, and integrations for cache, queues, sessions, and Scout. The default branch is `5.x`.

Read `AGENTS.md` and `CONTRIBUTING.md` before changing behavior. When a change alters user-facing behavior covered by the Laravel Boost skill, update the matching file under `resources/boost/skills/laravel-mongodb/`.

## Setup and checks

Tests need the MongoDB PHP extension and a running MongoDB server. `phpunit.xml.dist` uses `mongodb://mongodb` for Docker. Against a local server, override the URI. Hybrid SQL tests use an in-memory SQLite database.

```bash
composer install
MONGODB_URI="mongodb://127.0.0.1:27017" php -d zend.assertions=1 vendor/bin/phpunit --exclude-group atlas-search
vendor/bin/phpcs
vendor/bin/phpstan analyse --memory-limit=1G
composer dump-autoload --optimize --strict-psr
```

Docker equivalent: `docker-compose run app`.

Do not commit `vendor/` or `composer.lock`. This repository ignores the lock file.

## Conventions

- PHP `^8.2`, `declare(strict_types=1);`, Doctrine coding standard (`phpcs.xml.dist`).
- Import functions with `use function`.
- Every bug fix includes a regression test. Ticket regressions live in `tests/Ticket/`.
- Keep changes limited to the bug or feature being fixed.

## Hybrid many-to-many detach

`BelongsToMany` and `MorphToMany` store the relationship on the MongoDB document. The parent may be an SQL model that uses `HybridRelations`.

Call `pull()` only when `DocumentModel::isDocumentModel()` is true. An SQL model does not implement `pull()`, and calling it raises `BadMethodCallException`. Update that parent's loaded relation in memory instead.

`detach()` with no ids removes every related model. The loaded relation on an SQL parent must end up empty. `detach($id)` removes only that id from the MongoDB document and from the loaded relation.
