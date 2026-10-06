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
MONGODB_URI="mongodb://127.0.0.1:27017" vendor/bin/phpunit tests/Ticket/GH3530Test.php
composer run cs
vendor/bin/phpstan analyse --memory-limit=1G
```

Docker equivalent: `docker-compose run app`. Transaction tests need a replica set and are skipped or fail on a standalone server.

Do not commit `vendor/` or `composer.lock`. This repository ignores the lock file.

## Conventions

- PHP `^8.2`, `declare(strict_types=1);`, Doctrine coding standard (`phpcs.xml.dist`).
- Every bug fix includes a regression test. Ticket regressions live in `tests/Ticket/`.
- Keep changes limited to the bug or feature being fixed.

## Query log

`CommandSubscriber` logs each command through `Query\MongoshFormatter`. The Laravel query log, Telescope, and Debugbar receive a mongosh statement (`db.getCollection("items").find({...})`), not canonical extended JSON. Commands that do not map to a collection helper fall back to `db.runCommand(EJSON.parse(...))`.

Do not add a configuration option for the log format. The format is shared so every debugging tool can display it and so the statement can be pasted into mongosh. See GitHub issue 3530.
