# CLAUDE.md

Guidance for Claude Code when working on `mongodb/laravel-mongodb`.

See [AGENTS.md](AGENTS.md) for the repository-wide agent guidance, which takes precedence.
For helping end users *use* the package in their own applications, follow the
[`laravel-mongodb` skill](resources/boost/skills/laravel-mongodb/SKILL.md) instead.

## Project overview

Laravel MongoDB extends Eloquent and the Laravel query builder for MongoDB, using the original
Laravel API. It also provides MongoDB drivers for cache, sessions, queues, batches, filesystem
(GridFS) and Scout.

- `src/Query/` — query builder (`Builder`), MQL grammar and aggregation builder
- `src/Eloquent/` — `Model`, the `DocumentModel` trait, the Eloquent `Builder` and BSON casts
- `src/Relations/` — MongoDB-aware relation classes, including embedded relations
- `src/Schema/`, `src/Cache/`, `src/Session/`, `src/Queue/`, `src/Bus/`, `src/Scout/` — drivers
- `resources/boost/` — Laravel Boost guidelines and the agent skill shipped to end users
- `tests/` — PHPUnit suite; test models live in `tests/Models/`

## Setup

Requirements: PHP `^8.2` with the `mongodb` extension, Composer, and a MongoDB server. The test
suite expects a **single-node replica set** (transactions are tested).

```bash
composer install

# Local MongoDB replica set, mirroring .github/workflows/build-ci.yml
mongod --replSet rs --dbpath /tmp/mongo --port 27017 --bind_ip 127.0.0.1
mongosh --eval 'rs.initiate({_id:"rs",members:[{_id:0,host:"127.0.0.1:27017"}]})'
export MONGODB_URI="mongodb://127.0.0.1:27017/?replicaSet=rs"
```

Alternatively, `docker compose run app` installs dependencies and runs the suite in a container.

## Commands

```bash
php -d zend.assertions=1 vendor/bin/phpunit --exclude-group atlas-search   # what CI runs
vendor/bin/phpunit tests/Casts/ObjectIdTest.php                            # a single file
vendor/bin/phpunit --filter testMorph tests/RelationsTest.php              # a single test
composer run cs        # PHP_CodeSniffer (Doctrine coding standard)
composer run cs:fix    # auto-fix style issues
vendor/bin/phpstan analyse
php tests/skills/laravel-mongodb/validate-php-examples.php   # after editing skill docs
```

Tests tagged `@group atlas-search` need a MongoDB Atlas (or Atlas Local) deployment.

## Conventions

- `declare(strict_types=1)` in every file; import functions with `use function`.
- Follow the Doctrine coding standard enforced by `phpcs.xml.dist`; lines up to 120 characters.
- Mark overridden methods with `#[Override]`.
- Add or update tests for every behaviour change. Prefer real models from `tests/Models/` and
  assert observable results; check the stored BSON type with the raw collection when the type
  matters.
- Never break user applications: keep queries lenient (an unconvertible value is sent as-is) and
  keep validation of operator injection on `_id` and relation keys.
- Document user-facing changes in the skill reference files (`resources/boost/skills/...`) in the
  same PR, as required by `AGENTS.md`.
- Backwards compatibility matters: the package follows SemVer and supports Laravel 12 and 13.

## Key behaviours to know

- `id` is an alias of `_id`; both are exposed on models and aliased in queries by the grammar.
- Query values for `_id` and `*._id` are converted from 24-hex strings to `ObjectId` (and 16-byte
  strings to UUID `Binary`). Other fields are converted only when the model casts them with
  `MongoDB\Laravel\Eloquent\Casts\ObjectId` or `BinaryUuid`; the Eloquent builder registers these
  converters on the query builder via `Query\Builder::setKeyCasts()`.
- Operator documents (`['$ne' => ...]`) are rejected as `_id` or relation key values to prevent
  MQL injection; see `Query\Builder::assertKeyIsNotOperator()`.
- Bug tracking happens in JIRA (project `PHPLARA`); PR titles usually start with the ticket key.
