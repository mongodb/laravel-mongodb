# Contributing to `mongodb/laravel-mongodb`

Guidance for agents working on the development of this library. For assisting
end users who *use* the package in their own applications, see the
[`laravel-mongodb` skill](resources/boost/skills/laravel-mongodb/SKILL.md).

## Project direction

The package mirrors Eloquent and the Laravel database layer, so that an application can move a
model to MongoDB without rewriting its code. `illuminate/database` is the reference: when a
behaviour differs, align with Laravel instead of inventing a MongoDB-specific API.

The scope covers the Eloquent ORM (models, query builder, relations, casts), the aggregation
builder (built on the `mongodb/mongodb` aggregation builder), and the Laravel drivers and
integrations (queue, session, cache, auth, validation, filesystem, Scout, and so on).

When a Laravel feature cannot work on MongoDB, do not diverge silently: state the limitation, and
prefer a change in Laravel or in a dependency when that unblocks a faithful implementation.

## Repository layout

- `src/Query/`: query builder, MQL grammar and aggregation builder
- `src/Eloquent/`: `Model`, the `DocumentModel` trait, the Eloquent builder and the BSON casts
- `src/Relations/`: relation classes, including the embedded relations
- `src/Auth/`, `src/Bus/`, `src/Cache/`, `src/Queue/`, `src/Schema/`, `src/Scout/`, `src/Session/`, `src/Validation/`: drivers and integrations
- `resources/boost/`: Laravel Boost guidelines and the agent skill shipped to end users
- `tests/`: PHPUnit suite, with the test models in `tests/Models/`

## Setup

Requires PHP `^8.2` with the `mongodb` extension, Composer, and a MongoDB server. The suite needs a
replica set, because transactions are tested.

CI starts a single-node replica set with Docker:

```bash
docker run --name mongodb -p 27017:27017 --detach mongo:8.0 \
  mongod --replSet rs --setParameter transactionLifetimeLimitSeconds=5
mongosh --eval 'rs.initiate({_id:"rs",members:[{_id:0,host:"127.0.0.1:27017"}]})'
export MONGODB_URI="mongodb://127.0.0.1:27017/?replicaSet=rs"
```

To run `mongod` directly instead, create the data directory and fork the server, so the following
commands are reached:

```bash
mkdir -p /tmp/mongo
mongod --replSet rs --dbpath /tmp/mongo --port 27017 --bind_ip 127.0.0.1 \
  --fork --logpath /tmp/mongo/mongod.log
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
vendor/bin/phpunit                                                   # full suite
vendor/bin/phpunit tests/ModelTest.php                               # a single file
vendor/bin/phpunit --filter testEmbedsManySave tests/EmbeddedRelationsTest.php
vendor/bin/phpcs                                                     # PHP_CodeSniffer (Doctrine coding standard)
vendor/bin/phpcbf                                                    # auto-fix style issues
vendor/bin/phpstan analyse                                           # static analysis
vendor/bin/rector process --dry-run                                  # preview automated refactors
vendor/bin/rector process                                            # apply them
php tests/skills/laravel-mongodb/validate-php-examples.php           # after editing the skill examples
```

Tests marked with `#[Group('atlas-search')]` need Atlas or Atlas Local. CI excludes them from the
default run: `php -d zend.assertions=1 vendor/bin/phpunit --exclude-group atlas-search`.

## Conventions

- `declare(strict_types=1)` in every file, and import functions with `use function`.
- Follow the Doctrine coding standard enforced by `phpcs.xml.dist`.
- Mark methods that override a parent with `#[Override]`.
- Prefer `vendor/bin/phpcbf` and `vendor/bin/rector process` over manual edits for style and
  automated refactors. Exclude a phpcs sniff in `phpcs.xml.dist`, or add a rule in `rector.php`,
  only when the rule is buggy or makes the code less readable, and comment the reason.
- Add or update tests for every behaviour change. Prefer the models from `tests/Models/` and assert
  observable results; check the stored BSON with the raw collection when the type matters.
- Keep backwards compatibility: the package follows SemVer. The supported Laravel versions are the
  constraints declared in `composer.json`, read them there instead of assuming a version.

## Key behaviours to know

- `id` is an alias of `_id`. It is exposed on the models and aliased in queries by the grammar.
- `Query\Builder::convertKey()` converts an `_id` query value by its shape, after rejecting
  operator documents: a 24-character hex string becomes an `ObjectId`, and a 16-byte string
  containing a non-printable byte becomes a `Binary` of UUID subtype. Any other value is left
  unchanged. The conversion reads the value, not the model's declared casts.
- For an embedded `*._id`, `Query\Builder::castKey()` applies the same shape-based conversion
  without the operator check, so operator documents stay allowed on embedded ids.
- Operator documents (`['$ne' => ...]`) are rejected as an `_id` or a relation key value, to prevent
  MQL injection. See `Query\Builder::assertKeyIsNotOperator()`.

## Keeping the skill in sync

When a change adds, removes, or alters user-facing behaviour covered by the
[`laravel-mongodb` skill](resources/boost/skills/laravel-mongodb/SKILL.md), update the relevant
skill reference file in the same PR so the skill stays accurate.

To identify what needs to be changed, refer to the
[change logs](https://github.com/mongodb/laravel-mongodb/releases/).
