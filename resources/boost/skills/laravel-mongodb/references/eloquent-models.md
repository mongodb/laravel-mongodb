# Eloquent Models

## Base class

```php
// WRONG — standard Eloquent does not understand MongoDB types
use Illuminate\Database\Eloquent\Model;

// CORRECT
use MongoDB\Laravel\Eloquent\Model;
```

```php
<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

final class Movie extends Model
{
    protected $connection = 'mongodb';
    protected $table      = 'movies';   // $table not $collection
    protected $keyType    = 'string';   // ObjectId surfaced as string (already default)

    protected $fillable = ['title', 'year', 'released_at'];

    protected $casts = [
        'year'        => 'integer',
        'released_at' => 'datetime',
        // Do NOT cast native MongoDB arrays to 'array' — stored natively;
        // 'array' cast triggers deprecation and may serialize as JSON.
    ];
}
```

## `_id` vs `id`

- BSON field is `_id` (default ObjectId, but any BSON type can be used); PHP exposes both `$model->id` and `$model->_id`.
- In API resources, always cast an ObjectId `_id` to string so clients receive `"6708..."` not `{"$oid":"6708..."}`:

```php
public function toArray(Request $request): array
{
    return [
        'id'    => (string) $this->_id,  // or $this->id — equivalent
        'title' => $this->title,
    ];
}
```

## ObjectId casts for foreign keys

Eloquent coerces types during relation matching so `belongsTo()` works without explicit casts. Declare a cast on FK fields when values may come from outside model attributes (imports, raw ObjectIds) — it normalises the BSON type on write and prevents mismatches on direct `where()` queries.

```php
protected $casts = [
    'author_id' => 'string',   // simplest: store/read as string for Eloquent matching

    // OR preserve native BSON ObjectId in the database while exposing a string in PHP:
    // 'author_id' => MongoDB\Laravel\Eloquent\Casts\ObjectId::class,
    // (use the package cast, NOT \MongoDB\BSON\ObjectId::class)
];
```

Only `_id` and fields ending with `._id` are converted to `ObjectId` automatically in queries. Any other field — including
polymorphic keys such as `commentable_id` — is converted only when the model casts it with
`MongoDB\Laravel\Eloquent\Casts\ObjectId` (or `BinaryUuid`). The cast then applies to `where()`, `whereIn()`,
`firstOrCreate()` and relation queries (`hasMany`, `morphMany`, eager loading, `whereHas`), so the string exposed by the
model can be used directly:

```php
final class Comment extends Model
{
    protected $casts = ['commentable_id' => MongoDB\Laravel\Eloquent\Casts\ObjectId::class];

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
}

Comment::where('commentable_id', $post->id)->get();   // string is converted to ObjectId
$post->comments;                                       // morphMany matches the native ObjectId
```

A value that is not a valid 24-character hexadecimal string (or UUID for `BinaryUuid`) is sent as-is and matches nothing.

## Extending a non-MongoDB base class

```php
<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\DocumentModel;
use Vendor\Package\BaseModel;

final class AuditLog extends BaseModel
{
    use DocumentModel;

    protected $connection = 'mongodb';
    protected $table      = 'audit_logs';
    protected $keyType    = 'string';
}
```

## Encryption

```php
protected $casts = [
    'ssn' => 'encrypted',   // Laravel encrypted cast; use Queryable Encryption for server-side equality
];
```
