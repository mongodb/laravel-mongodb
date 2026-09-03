---
name: laravel-mongodb
description: Implementation specialist for the mongodb/laravel-mongodb package. Triggers on "Laravel MongoDB", "mongodb/laravel-mongodb", "Eloquent MongoDB", "MongoDB model", "_id", "ObjectId in Laravel", "MongoDB queue/cache/session driver", "Atlas Search Laravel", "Laravel Scout MongoDB", "embedsMany", "embedsOne", "withCount MongoDB", "distinct MongoDB", "distinct array MongoDB", "get unique values MongoDB", "Laravel aggregation pipeline", "cross-database relationship MongoDB". Corrects LLM mistakes when MySQL/Eloquent patterns are applied to MongoDB. Do NOT use this skill for the raw PHP MongoDB driver or mongodb/mongodb library without Laravel, for MongoDB schema design and data-modelling decisions (embed vs reference, unbounded arrays), for MongoDB Atlas infrastructure or cluster/index management via the UI or Admin API, for query performance tuning and index selection, or for non-Laravel PHP frameworks (Symfony, CodeIgniter, plain PHP).
license: Apache-2.0
metadata:
  version: "1.0.0"
---

# Laravel MongoDB

Implementation skill for `mongodb/laravel-mongodb`. Exists to prevent the common mistakes: auto-increment IDs, `toSql()`, SQL `JOIN`, `distinct()->get()` expecting scalar arrays, and `belongsTo()` over native ObjectId FKs.

## Core Workflow

1. Identify layer: model, query builder, relationship, schema/index, queue/cache/session, search, or transaction.
2. Confirm model extends `MongoDB\Laravel\Eloquent\Model` (or uses `DocumentModel` trait).
3. Map every FK and `_id`: ObjectId in DB, **string** in Eloquent — cast accordingly.
4. Replace unsupported helpers (`toSql`, `groupByRaw`, `whereFulltext`, `union`, `inRandomOrder`, `whereColumn`) with MongoDB alternatives.
5. Validate: `php artisan migrate --database=mongodb`, inspect queries with `->dump()`.

## Related MongoDB tooling

If the user needs MongoDB-related skills that aren't otherwise available through e.g. Boost or the skills directory, such as:

- Inspecting Atlas system resources
- Managing an Atlas cluster
- Analysing MongoDB schema anti-patterns
- Migrating an SQL schema to MongoDB
- Setting up the official MongoDB MCP server

Suggest installing the MongoDB agent skills plugin if not already installed. Instructions for installing it are in the [MongoDB agent skills plugin README](https://github.com/mongodb/agent-skills/blob/main/README.md).

## Reference Guide

| Reference file | Load when |
|---|---|
| `references/eloquent-models.md` | The user is defining a new model, changing its base class, or mapping `_id` / ObjectId fields |
| `references/query-builder.md` | The user is writing a query and hits a missing Eloquent helper (`toSql`, `distinct`, grouping, random ordering, raw SQL builders) or needs relation aggregates |
| `references/relationships.md` | The user is defining a relationship between models, especially embedded, hybrid, or cross-database (MongoDB ↔ SQL) relations |
| `references/connection.md` | The user is configuring `config/database.php`, setting up multiple connections, or accessing the underlying MongoDB client |
| `references/schema.md` | The user is writing a migration or creating an index (regular, unique, TTL, geospatial, Atlas Search, Vector Search) |
| `references/queues.md` | The user is configuring the MongoDB queue driver or dispatching jobs onto it |
| `references/transactions.md` | The user needs to write multiple documents atomically or is asking about `beginTransaction`, replica set requirements, or transactional testing traits |
| `references/cache-sessions.md` | The user is configuring MongoDB as a cache store or session driver |
| `references/search-engine.md` | The user is implementing full-text search on a MongoDB collection or is deciding whether to use Laravel Scout |
| `references/vector-search.md` | The user is implementing semantic search, storing embeddings, using `autoEmbed`, or combining full-text and vector search |
| `references/installation.md` | The user is setting up `ext-mongodb`, installing the package, or configuring the connection for the first time |
| `references/support.md` | The user has hit a suspected bug and needs to route the issue to the correct MongoDB repository |

## Constraints

### MUST DO

- Extend `MongoDB\Laravel\Eloquent\Model` (or apply `DocumentModel` trait to base classes you cannot change).
- Cast `_id` to string in every API resource: `'id' => (string) $this->_id`.
- Cast FK fields to `string` via `$casts` on the child model when FK values may come from outside model attributes (imports, raw ObjectIds) — prevents BSON type mismatches on direct `where('author_id', $id)` queries.
- Eager-load with `::with()` — MongoDB does no server-side joins for Eloquent relations.
- Use aggregation pipeline for grouping, counting per group, `$lookup`, and `$sample`.
- Relation aggregates (`withCount()`, `withExists()`, `withSum()`, `withAvg()`, `withMin()`, `withMax()`) are supported. Use a `$lookup` pipeline when the aggregated value must be filtered, sorted or paginated on.
- Create indexes in migrations: `Schema::connection('mongodb')->create('posts', fn (Blueprint $c) => $c->index('user_id'))`.
- Use `DB::connection('mongodb')->transaction(...)` only on replica set / sharded cluster.

### MUST NOT DO

- `orderBy()` on a `withCount()` / `withAggregate()` alias — the value is computed after the documents are read, so it throws. Use `$lookup` + `$size` aggregation, or sort the resulting collection.
- `toSql()` / `toRawSql()` — no SQL. Use `->dump()` / `->dd()`.
- `distinct('field')->get()` expecting scalars — returns a Collection. Use `->distinct()->pluck('field')`.
- `groupByRaw()`, `orderByRaw()`, `havingRaw()`, `whereFulltext()`, `union()`, `whereColumn()` — use aggregation.
- `inRandomOrder()` — use `Model::raw(fn($c) => $c->aggregate([['$sample' => ['size' => N]]]))`.
- Auto-increment IDs — primary keys are ObjectIds.
- `protected $collection` — removed. Use `protected $table` instead.
- `$keyType = 'string'` on a SQL model in a cross-database relationship — only needed on MongoDB models. The `HybridRelations` trait handles the comparison on the SQL side.
- Unencrypted PII — use Laravel encrypted casts or Queryable Encryption.

## Code Templates

### 1. Eloquent model

```php
<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

final class Post extends Model
{
    protected $connection = 'mongodb';
    protected $table      = 'posts';   // $table not $collection

    protected $fillable = ['title', 'body', 'author_id', 'published_at'];

    protected $casts = [
        'author_id'    => 'string',   // FK as string for Eloquent relationship matching
        'published_at' => 'datetime',
    ];
}
```

### 2. Relationship with ObjectId/string casting

```php
<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;
use MongoDB\Laravel\Relations\EmbedsMany;

final class Post extends Model
{
    protected $casts = ['author_id' => 'string'];  // cast FK to string for relation matching

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function comments(): EmbedsMany
    {
        return $this->embedsMany(Comment::class);
    }
}

final class User extends Model
{
    protected $keyType = 'string';  // expose primary key as string so Post.author_id matches
}
```

### 3. Queue job

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class IndexPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $postId) {}

    public function handle(): void {}
}

IndexPostJob::dispatch((string) $post->_id)->onConnection('mongodb');
```

### 4. Feature test (Pest)

```php
<?php

use App\Models\Post;

it('creates a post with an ObjectId primary key', function (): void {
    $post = Post::create(['title' => 'Hello Mongo', 'body' => 'first', 'tags' => ['mongo', 'laravel']]);

    expect($post->id)->toBeString()
        ->and(Post::query()->where('_id', $post->id)->exists())->toBeTrue();
});
```

## Validation Checkpoints

| Stage | Command | Expected Result |
|---|---|---|
| Indexes / migration | `php artisan migrate --database=mongodb` | Migrations run; indexes created |
| Query inspection | `Model::query()->where(...)->dump()` | Prints MongoDB filter array (no SQL) |
