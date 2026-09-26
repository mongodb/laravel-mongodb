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

## Polymorphic relationships

`morphOne`, `morphMany`, `morphTo`, `morphToMany` and `morphedByMany` use the [Laravel API](https://laravel.com/docs/eloquent-relationships#polymorphic-relationships) unchanged; only the storage differs. The `Illuminate\Database\Eloquent\Relations\Morph*` return types are always valid: the package returns its `MongoDB\Laravel\Relations\Morph*` subclasses, except for a `morphTo()` that resolves to a SQL model.

### One-to-one / one-to-many (`morphOne`, `morphMany`, `morphTo`)

The child document stores two fields: `{name}_id` (the parent key, a string) and `{name}_type` (the parent's morph class — the FQCN, or the alias when a morph map is enforced).

```php
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use MongoDB\Laravel\Eloquent\Model;

final class Photo extends Model
{
    public function imageable(): MorphTo
    {
        return $this->morphTo();  // reads imageable_id + imageable_type
    }
}

final class User extends Model
{
    public function photos(): MorphMany
    {
        return $this->morphMany(Photo::class, 'imageable');
    }
}

final class Client extends Model
{
    public function photo(): MorphOne
    {
        return $this->morphOne(Photo::class, 'imageable');
    }
}

$user->photos()->create(['url' => 'a.jpg']);
// photo: { url: "a.jpg", imageable_id: "<user id>", imageable_type: "App\Models\User" }

$photo->imageable()->associate($client)->save();
$photo->imageable()->dissociate()->save();  // sets imageable_id and imageable_type to null

Photo::with('imageable')->get();  // one extra query per distinct imageable_type
Photo::whereHasMorph('imageable', [User::class], fn ($q) => $q->where('name', 'John'))->get();
User::has('photos')->withCount('photos')->get();
```

- `Relation::enforceMorphMap([...])` in a service provider works as in Laravel and shortens the stored `{name}_type`. A stored type that does not resolve to an Eloquent model throws `InvalidArgumentException` instead of instantiating an arbitrary class.
- `morphTo()->withTrashed()`, `constrain()` and `morphWith()` are supported.
- Not supported: `whereHasMorph($relation, '*')` — list the types explicitly. `has()`, `whereHas()` and `withCount()` **on a `morphTo`** — use `whereHasMorph()` instead (the other direction, `has('photos')`, works).

### Many-to-many (`morphToMany`, `morphedByMany`)

No pivot collection. The parent stores the related ids in `{related}_ids`; the related model stores an array of `{ {name}_id, {name}_type }` subdocuments in `{name}s`, which `morphedByMany()` reads.

```php
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use MongoDB\Laravel\Eloquent\Model;

final class Post extends Model
{
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');  // post.tag_ids
    }
}

final class Tag extends Model
{
    public function posts(): MorphToMany
    {
        return $this->morphedByMany(Post::class, 'taggable');  // tag.taggables[*].taggable_id matched against posts._id
    }

    public function videos(): MorphToMany
    {
        return $this->morphedByMany(Video::class, 'taggable');
    }
}

$post->tags()->attach($tag);
// post: { tag_ids: ["<tag id>"] }
// tag:  { taggables: [{ taggable_id: "<post id>", taggable_type: "App\Models\Post" }] }

$post->tags()->sync([$tag1->id, $tag2->id]);  // updates both documents
$tag->posts()->detach($post);
Tag::with('posts', 'videos')->get();
Post::has('tags', '>=', 2)->withCount('tags')->get();
```

Pivot attributes are not stored: `withPivot()` is silently ignored, `wherePivot()` matches nothing and `toggle()` throws — use `attach()` / `detach()` / `sync()`. A polymorphic relation whose parent is a SQL model needs `HybridRelations` on that SQL model — see *Cross-database relationships*.

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
