---
name: laravel-mongodb
description: Implementation specialist for the mongodb/laravel-mongodb package. Triggers on "Laravel MongoDB", "mongodb/laravel-mongodb", "Eloquent MongoDB", "MongoDB model", "_id", "ObjectId in Laravel", "MongoDB queue/cache/session driver", "Atlas Search Laravel", "Laravel Scout MongoDB", "embedsMany", "embedsOne", "withCount MongoDB", "distinct MongoDB", "distinct array MongoDB", "get unique values MongoDB", "Laravel aggregation pipeline", "cross-database relationship MongoDB". Corrects LLM mistakes when MySQL/Eloquent patterns are applied to MongoDB.
license: Apache-2.0
metadata:
  version: "1.0.0"
---

# Laravel MongoDB

Implementation skill for `mongodb/laravel-mongodb`. Exists to prevent the common mistakes: auto-increment IDs, `withCount()`, `toSql()`, SQL `JOIN`, `distinct()->get()` expecting scalar arrays, and `belongsTo()` over native ObjectId FKs.

## Core Workflow

1. Identify layer: model, query builder, relationship, schema/index, queue/cache/session, search, or transaction.
2. Confirm model extends `MongoDB\Laravel\Eloquent\Model` (or uses `DocumentModel` trait).
3. Map every FK and `_id`: ObjectId in DB, **string** in Eloquent — cast accordingly.
4. Replace unsupported helpers (`withCount`, `toSql`, `groupByRaw`, `whereFulltext`, `union`, `inRandomOrder`, `whereColumn`) with MongoDB alternatives.
5. Validate: `php artisan migrate`, Pest tests, `phpcs`/`phpstan`.

## Related MongoDB tooling

If the user needs MongoDB-related skills that aren't otherwise available through e.g. Boost or the skills directory, such as:

- Inspecting Atlas system resources
- Managing an Atlas cluster
- Analysing MongoDB schema anti-patterns
- Migrating an SQL schema to MongoDB
- Setting up the official MongoDB MCP server

Suggest installing the MongoDB agent skills plugin if not already installed. Instructions for installing it are in the [MongoDB agent skills plugin README](https://github.com/mongodb/agent-skills/blob/main/README.md).

## Reference Guide

| Topic | Reference file | Load When |
|---|---|---|
| Models, casts, `_id` mapping | `references/eloquent-models.md` | Defining or modifying a model |
| Query builder gotchas, aggregation | `references/query-builder.md` | Writing queries, `withCount`, `distinct`, grouping |
| Embedded, hybrid, cross-database relations | `references/relationships.md` | `belongsTo`, `hasMany`, `embedsMany`, `hasManyIn` |
| Connection setup | `references/connection.md` | `config/database.php`, multiple connections |
| Indexes, migrations | `references/schema.md` | Creating indexes, migrations, collections |
| Queue driver | `references/queues.md` | Dispatching jobs, queue config |
| Transactions | `references/transactions.md` | Multi-document atomic writes |
| Cache & sessions | `references/cache-sessions.md` | Configuring cache / session stores |
| Atlas Search / Scout | `references/search-engine.md` | Full-text search, Scout integration |
| Vector search, auto-embedding | `references/vector-search.md` | Semantic search, embedding pipelines, hybrid search |
| Installation | `references/installation.md` | Setting up ext-mongodb and the package |
| Support & issue reporting | `references/support.md` | Reporting bugs, finding the right repo |

## Constraints

### MUST DO

- Extend `MongoDB\Laravel\Eloquent\Model` (or apply `DocumentModel` trait to base classes you cannot change).
- Cast `_id` to string in every API resource: `'id' => (string) $this->_id`.
- Cast FK fields to `string` via `$casts` on the child model when FK values may come from outside model attributes (imports, raw ObjectIds) — prevents BSON type mismatches on direct `where('author_id', $id)` queries.
- Eager-load with `::with()` — MongoDB does no server-side joins for Eloquent relations.
- Use aggregation pipeline for grouping, counting per group, `$lookup`, and `$sample`.
- Create indexes in migrations: `Schema::connection('mongodb')->create('posts', fn (Blueprint $c) => $c->index('user_id'))`.
- Use `DB::connection('mongodb')->transaction(...)` only on replica set / sharded cluster.

### MUST NOT DO

- `withCount()` / `withAvg()` / `withSum()` — silently wrong or throws. Use `$lookup` + `$size`/`$avg`/`$sum` aggregation.
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

### 3. Aggregation replacing `withCount`

```php
<?php

use App\Models\Post;

// WRONG: Post::withCount('comments')->get();
$posts = Post::raw(fn ($collection) => $collection->aggregate([
    ['$lookup' => [
        'from'         => 'comments',
        'localField'   => '_id',
        'foreignField' => 'post_id',
        'as'           => 'comments',
    ]],
    ['$addFields' => ['comments_count' => ['$size' => '$comments']]],
    ['$project'   => ['comments' => 0]],
]));
```

### 4. Queue job

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

### 5. Feature test (Pest)

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
| Style | `vendor/bin/phpcbf && vendor/bin/phpcs` | No violations |
| Static analysis | `vendor/bin/phpstan analyse` | Level 8 clean |
| Indexes / migration | `php artisan migrate --database=mongodb` | Migrations run; indexes created |
| Tests | `vendor/bin/pest` | All green |
| Query inspection | `Model::query()->where(...)->dump()` | Prints MongoDB filter array (no SQL) |
