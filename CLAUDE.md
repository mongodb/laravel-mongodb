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
MONGODB_URI="mongodb://127.0.0.1:27017" vendor/bin/phpunit tests/Ticket/GH2986Test.php
composer run cs
vendor/bin/phpstan analyse --memory-limit=1G
```

Docker equivalent: `docker-compose run app`. Transaction tests need a replica set and are skipped or fail on a standalone server.

Do not commit `vendor/` or `composer.lock`. This repository ignores the lock file.

## Conventions

- PHP `^8.2`, `declare(strict_types=1);`, Doctrine coding standard (`phpcs.xml.dist`).
- Every bug fix includes a regression test. Ticket regressions live in `tests/Ticket/`.
- Keep changes limited to the bug or feature being fixed.

## ObjectId relations and whereHas

`DocumentModel::getIdAttribute()` exposes `_id` as a string. `Eloquent\Casts\ObjectId` stores a foreign key as BSON and also returns a string when the attribute is read. A direct `where('entity_a_id', $hexString)` therefore does not match, which `tests/Casts/ObjectIdTest.php` locks in.

`whereHas` plucks those strings and puts them in `$in`. For a foreign key cast with `ObjectId`, `QueriesRelationships::castIdsForObjectIdConstraint()` converts them back to BSON before the parent query, so `whereHas` matches the stored values. Do not stop stringifying ids in `getIdAttribute()`; that would change `$model->id` for every model. See GitHub issue 2986.
