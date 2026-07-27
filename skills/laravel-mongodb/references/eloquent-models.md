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

Eloquent coerces types during relation matching so `belongsTo()` works without explicit casts. Add `'author_id' => 'string'` in `$casts` when FK values may come from outside model attributes (imports, raw ObjectIds) — it normalises the BSON type on write and prevents mismatches on direct `where()` queries.

```php
protected $casts = [
    'author_id' => 'string',   // simplest: store/read as string for Eloquent matching

    // OR preserve native BSON ObjectId:
    // 'author_id' => MongoDB\Laravel\Eloquent\Casts\ObjectId::class,
    // (use the package cast, NOT \MongoDB\BSON\ObjectId::class)
];
```

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
