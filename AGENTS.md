# Contributing to `mongodb/laravel-mongodb`

Guidance for agents working on the development of this library. For assisting
end users who *use* the package in their own applications, see the
[`laravel-mongodb` skill](resources/boost/skills/laravel-mongodb/SKILL.md).

## Repository layout

- `src/Query/` — query builder, MQL grammar and aggregation builder
- `src/Eloquent/` — `Model`, the `DocumentModel` trait, the Eloquent builder and the BSON casts
- `src/Relations/` — relation classes, including the embedded relations
- `src/Auth/`, `src/Bus/`, `src/Cache/`, `src/Queue/`, `src/Schema/`, `src/Scout/`, `src/Session/`, `src/Validation/` — drivers and integrations
- `resources/boost/` — Laravel Boost guidelines and the agent skill shipped to end users
- `tests/` — PHPUnit suite, with the test models in `tests/Models/`

## Setup

Requires PHP `^8.2` with the `mongodb` extension, Composer, and a MongoDB server. The suite needs a
replica set, because transactions are tested.

CI starts a single-node replica set:

```bash
mongod --replSet rs --dbpath /tmp/mongo --port 27017 --bind_ip 127.0.0.1
mongosh --eval 'rs.initiate({_id:"rs",members:[{_id:0,host:"127.0.0.1:27017"}]})'
export MONGODB_URI="mongodb://127.0.0.1:27017/?replicaSet=rs"
```

For development, MongoDB Atlas Local is a single-node replica set that also supports Atlas Search.
Start it with the Atlas CLI or with Docker:

```bash
atlas deployments setup --type local   # Atlas CLI
docker compose up -d mongodb           # mongodb/mongodb-atlas-local:8, exposed on port 27017
```

Then run `composer install`, or `docker compose run app` to install the dependencies and run the
suite in a container.

## Commands

```bash
composer test                                                        # full suite
vendor/bin/phpunit tests/ModelTest.php                               # a single file
vendor/bin/phpunit --filter testEmbedsManySave tests/EmbeddedRelationsTest.php
composer run cs                                                      # PHP_CodeSniffer (Doctrine coding standard)
composer run cs:fix                                                  # auto-fix style issues
vendor/bin/phpstan analyse
php tests/skills/laravel-mongodb/validate-php-examples.php           # after editing the skill examples
```

Tests marked with `#[Group('atlas-search')]` need Atlas or Atlas Local. CI excludes them from the
default run: `php -d zend.assertions=1 vendor/bin/phpunit --exclude-group atlas-search`.

## Conventions

- `declare(strict_types=1)` in every file, and import functions with `use function`.
- Follow the Doctrine coding standard enforced by `phpcs.xml.dist`.
- Mark methods that override a parent with `#[Override]`.
- Add or update tests for every behaviour change. Prefer the models from `tests/Models/` and assert
  observable results; check the stored BSON with the raw collection when the type matters.
- Keep backwards compatibility: the package follows SemVer and supports Laravel 12 and 13.

## Key behaviours to know

- `id` is an alias of `_id`. It is exposed on the models and aliased in queries by the grammar.
- A query value for `_id` is converted from a 24-hex string to `ObjectId` by
  `Query\Builder::convertKey()`. For an embedded `*._id`, `Query\Builder::castKey()` applies the
  cast declared on the model (`MongoDB\Laravel\Eloquent\Casts\ObjectId` or `BinaryUuid`).
- Operator documents (`['$ne' => ...]`) are rejected as an `_id` or a relation key value, to prevent
  MQL injection. See `Query\Builder::assertKeyIsNotOperator()`.

## Keeping the skill in sync

When a change adds, removes, or alters user-facing behaviour covered by the
[`laravel-mongodb` skill](resources/boost/skills/laravel-mongodb/SKILL.md), update the relevant
skill reference file in the same PR so the skill stays accurate.

To identify what needs to be changed, refer to the
[change logs](https://github.com/mongodb/laravel-mongodb/releases/).
