# Relationships

## FK and primary key types

Eloquent coerces types during relation matching, so `belongsTo()` works without explicit casts. Add a `string` cast on FK fields when values may come from outside model attributes (imports, raw ObjectIds) to normalise the BSON type on write:

```php
final class Post extends Model
{
    protected $casts = ['author_id' => 'string'];  // optional but recommended when FK source is uncertain

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}

final class User extends Model
{
    protected $keyType = 'string';
}
```

Use MongoDB-aware relation classes when in doubt:

```php
use MongoDB\Laravel\Relations\BelongsTo;
use MongoDB\Laravel\Relations\HasMany;
```

## Embedded documents

Embedded relations live inside the parent document — no second collection, no FK.

```php
<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\EmbedsMany;
use MongoDB\Laravel\Relations\EmbedsOne;

final class Post extends Model
{
    public function comments(): EmbedsMany
    {
        return $this->embedsMany(Comment::class);
    }

    public function author(): EmbedsOne
    {
        return $this->embedsOne(Author::class);
    }
}

$post->comments()->create(['body' => 'hi']);
$post->comments->where('approved', true);
```

`Comment` / `Author` extend `MongoDB\Laravel\Eloquent\Model` but are never persisted standalone.

## Cross-database relationships (MongoDB ↔ SQL)

**Rule:** `HybridRelations` goes on the **SQL model only** — never on the MongoDB model.

The SQL table must store the MongoDB `_id` as a **string column** (`VARCHAR(24)`).

```php
// SQL model (e.g. User in MySQL) — HybridRelations MUST be here, on the SQL side
use MongoDB\Laravel\Eloquent\HybridRelations;

final class User extends \Illuminate\Database\Eloquent\Model
{
    use HybridRelations;  // ONLY on the SQL model — do NOT add to the MongoDB model

    public function posts(): \MongoDB\Laravel\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Post::class, 'user_id');
    }
}

// MongoDB model (e.g. Post) — no HybridRelations needed here
final class Post extends \MongoDB\Laravel\Eloquent\Model
{
    protected $casts = ['user_id' => 'string'];  // cast FK to string for direct queries

    public function user(): \MongoDB\Laravel\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
```

## Eager loading

MongoDB cannot join Eloquent relations server-side (except via `$lookup`). Every `with()` is an extra round-trip — use it deliberately:

```php
$posts = Post::with(['author', 'tags'])->get();
```
