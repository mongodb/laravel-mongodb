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
MONGODB_URI="mongodb://127.0.0.1:27017" vendor/bin/phpunit tests/Ticket/GH3447Test.php
composer run cs
vendor/bin/phpstan analyse --memory-limit=1G
```

Docker equivalent: `docker-compose run app`. Transaction tests need a replica set and are skipped or fail on a standalone server.

Do not commit `vendor/` or `composer.lock`. This repository ignores the lock file.

## Conventions

- PHP `^8.2`, `declare(strict_types=1);`, Doctrine coding standard (`phpcs.xml.dist`).
- Every bug fix includes a regression test. Ticket regressions live in `tests/Ticket/`.
- Mirror Laravel's `Illuminate\Database\Connection` semantics where MongoDB has an equivalent, so Laravel testing traits and tooling keep working.
- Keep changes limited to the bug or feature being fixed.

## Connection lifecycle

`Connection` holds a `MongoDB\Client` and a `MongoDB\Database`. `disconnect()` sets both to null; the next call to `getClient()`, `getDatabase()`, `getCollection()`, or a query reconnects through `reconnectIfMissingConnection()`, which mirrors how the base Laravel connection re-creates its PDO handle. Never dereference `$this->connection` or `$this->db` directly, go through the getters.

Query logging uses a `CommandSubscriber` attached to the client. `disconnect()` only detaches it from the client being released; `$loggingQueries` is untouched, and the subscriber is attached again on reconnect. Laravel's `DatabaseMigrations` and `RefreshDatabase` traits call `disconnect()` after `migrate:fresh`, which is why this matters. See GitHub issue 3447.
