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

The package runs on every MongoDB topology (standalone, replica set and sharded cluster) and every
supported server version. Do not assume a topology or a version, the same code must work on all of
them.

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

For development, MongoDB Atlas Local gives a single-node replica set plus Atlas Search and vector
search, so it also runs the `atlas-search` tests. Start it with Docker or the Atlas CLI:

```bash
docker run --name mongodb -p 27017:27017 --detach mongodb/mongodb-atlas-local:8
# or
atlas deployments setup --type local
```

It takes a moment to start and the server restarts a few times while it initialises, so wait for
`db.runCommand("ping").ok` to stay stable instead of trusting a single probe. Then point the suite
at it:

```bash
export MONGODB_URI="mongodb://127.0.0.1:27017/?directConnection=true"
```

`docker compose up -d mongodb` runs the same image and waits for its health check.

The default CI job runs a plain `mongo:8.0` replica set instead. It is lighter but has no Atlas
Search, so the `atlas-search` group is excluded:

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

## Tests

The suite has two kinds of tests, split by what they need to run.

Unit tests need no server. They extend `PHPUnit\Framework\TestCase` and stub the connection.
`tests/Query/BuilderTest.php` builds a `Query\Builder` with a stubbed `Connection`, compiles with
`toMql()` and compares the MQL array; `tests/Eloquent/ModelTest.php` covers class-level behaviour.
Compare MQL with `assertEquals`, not `assertSame`, because it can contain BSON objects. Parameterise
cases with `#[DataProvider]` and `#[TestWith]`.

Functional tests need a MongoDB server. They extend `MongoDB\Laravel\Tests\TestCase`, which boots
Orchestra Testbench, registers the package providers and loads `tests/config/database.php`. The
`mongodb` connection reads `MONGODB_URI` and `MONGODB_DATABASE` (default `unittest`), with short
timeouts, and `phpunit.xml.dist` sets the defaults. Use the models from `tests/Models/`, which
include SQL models that guard the plain Eloquent integration.

Reach the driver collection with `DB::connection('mongodb')->getCollection('users')` or
`$this->getConnection('mongodb')`. Assert the observable result through Eloquent, and the stored BSON
through the raw collection when the type matters.

There is no global reset: each test creates and drops its own collections in `setUp()`. The runner
is strict, with `failOnRisky`, `failOnWarning` and `beStrictAboutOutputDuringTests` on, so a test
without an assertion or with output fails. Run with `php -d zend.assertions=1` to evaluate the
`assert()` calls, freeze time with `Carbon::setTestNow()`, and replay a case with and without a
transaction with `#[TestWith]`.

Some tests are conditional. `#[Group('atlas-search')]` needs Atlas or Atlas Local and is excluded by
default. `TestCase::skipIfQEIsNotSupported()` and `skipIfSearchIndexManagementIsNotSupported()` skip
when the server, the driver or the version does not support the feature. Transactions and Queryable
Encryption need a replica set or a sharded cluster, not a standalone.

## Conventions

- `declare(strict_types=1)` in every file, and import functions with `use function`.
- Follow the Doctrine coding standard enforced by `phpcs.xml.dist`.
- Mark methods that override a parent with `#[Override]`.
- Reuse before inventing. Look for the logic in this package, in Laravel, or in the driver before
  writing array manipulation or string formatting of your own. Build MQL with the `mongodb/mongodb`
  builders — `MongoDB\Builder\Stage`, `Expression`, `Accumulator`, `Pipeline` — instead of
  hand-written arrays and operator strings; `Collection::aggregate()` accepts the builder stages
  natively and encodes them itself.
- Prefer `vendor/bin/phpcbf` and `vendor/bin/rector process` over manual edits for style and
  automated refactors. Exclude a phpcs sniff in `phpcs.xml.dist`, or add a rule in `rector.php`,
  only when the rule is buggy or makes the code less readable, and comment the reason.
- Add or update tests for every behaviour change. Prefer the models from `tests/Models/` and assert
  observable results; check the stored BSON with the raw collection when the type matters.
- Keep backwards compatibility: the package follows SemVer. The supported Laravel versions are the
  constraints declared in `composer.json`, read them there instead of assuming a version.
- Detect a document model with `MongoDB\Laravel\Eloquent\Model::isDocumentModel($model)`, never with
  `instanceof`: a document model can use the `DocumentModel` trait without extending the model class.

## Review guidelines

A change is reviewed against the following standards, in rough order of priority.

1. Backwards compatibility. The package follows SemVer, so a behaviour change goes to the next
   minor branch, never to a patch branch, and is noted as a behaviour change in the release notes.
   The documented and tested behaviour is a contract. A more restrictive behaviour is not a bug fix
   unless it was only accidental.
2. Do not duplicate server validation. MongoDB and its drivers validate configuration and
   operators, and the server can widen what it accepts. Add a client-side check only to protect
   against something the server cannot, such as MQL injection.
3. Follow Laravel. Reuse the parent Eloquent method instead of reimplementing it, align the
   generated MQL with the SQL Laravel produces for the same call, and do not add parameters to a
   method that overrides a Laravel method.
4. Fail loudly. A public method that cannot express the requested constraint throws with a clear
   message instead of returning a query that matches the wrong documents. When a feature is not
   supported by the server version or the topology in use, let the server decide, then translate its
   error into a clear, actionable message instead of letting it surface raw, as
   `Schema\Builder::isAtlasSearchNotSupportedException()` does for Atlas Search.
5. Keep the API surface small. Mark an extension point that is not public API with `@internal`.
   Inline a helper that is used once and has no complexity.
6. Keep documentation short. Show usage with a small code sample, and drop implementation details,
   cost notes, and sentences that repeat the docblock above.
7. Name things for their intention. A method or command name should say what it does, not how.
8. Test behaviour, not code paths. One test per behaviour, with a data provider when several cases
   share a shape, and the reverse order when ordering matters. A new test must fail without the
   change, and must not pass for the wrong reason.
9. Never reject without a path forward. When a change is declined, name the alternative, the
   workaround, or the upstream fix in Laravel or a dependency.

Review with evidence: reproduce a claim before asserting it, show the value before and after, cite
the Laravel source, and prefer a concrete suggestion.

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

## Keeping this file current

Update this file in the same change whenever the change makes it stale: the repository layout, the
setup, the commands, the conventions, the review guidelines, or a key behaviour. When a review
misses a problem, or a pull request written by an agent repeats a mistake, add the lesson here so
the next agent applies it.
## Keeping the skill in sync

When a change adds, removes, or alters user-facing behaviour covered by the
[`laravel-mongodb` skill](resources/boost/skills/laravel-mongodb/SKILL.md), update the relevant
skill reference file in the same PR so the skill stays accurate.

To identify what needs to be changed, refer to the
[change logs](https://github.com/mongodb/laravel-mongodb/releases/).
