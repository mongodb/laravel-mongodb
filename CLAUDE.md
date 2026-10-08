# CLAUDE.md

Guidance for coding agents working in this repository.

## Project

`mongodb/laravel-mongodb` is the official Laravel integration for MongoDB. It provides an Eloquent model, query builder, schema builder, and integrations for cache, queues, sessions, and Scout. The default branch is `5.x`.

Read `AGENTS.md` and `CONTRIBUTING.md` before changing behavior. User-facing behavior that the Laravel Boost skill covers also needs an update under `resources/boost/skills/laravel-mongodb/`.

## Setup and checks

A MongoDB server must be running. `phpunit.xml.dist` points at `mongodb://mongodb` for Docker. Against a local server, override the URI:

```bash
composer install
MONGODB_URI="mongodb://127.0.0.1:27017" vendor/bin/phpunit
MONGODB_URI="mongodb://127.0.0.1:27017" vendor/bin/phpunit tests/Ticket/GH3549Test.php
composer run cs
vendor/bin/phpstan analyse --memory-limit=1G
```

Docker equivalent: `docker-compose run app`.

Do not commit `vendor/` or `composer.lock`. This repository ignores the lock file.

## Conventions

- PHP `^8.2`, `declare(strict_types=1);`, Doctrine coding standard (`phpcs.xml.dist`).
- Every bug fix includes a regression test. Ticket regressions live in `tests/Ticket/`.
- Prefer the existing extension points. `Query\Grammar::prepareFieldsForQuery()` and `prepareFieldsForResult()` are the supported way to change `id` / `_id` aliasing.
- Keep changes limited to the bug or feature being fixed.

## `id` and `_id`

By default, a root `id` is an alias of MongoDB `_id`. `Grammar::prepareFieldsForQuery()` renames `id` to `_id` on write, and results alias `_id` back to `id` when no separate `id` is present. Eloquent then exposes both `$model->id` and `$model->_id`.

Applications may override the grammar (often via a custom connection) so an integer `id` is stored beside a generated ObjectId `_id`. After `Model::create()` / `save()`, that assigned `id` must stay on the in-memory model. The generated `_id` is recorded on the same instance. See `Query\Builder::insertGetId()` and `DocumentModel::insertAndSetId()`, and GitHub issue 3549.
