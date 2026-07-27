# Atlas Search

## Scout is optional

**With Atlas Search on a MongoDB collection, no sync pipeline is needed.** Atlas indexes the collection directly — documents are automatically indexed on insert or update, with no Scout import job to run.

Use Scout only if you need the `Searchable` trait API (`Model::search()`, `paginate()`, cross-driver portability). For full control or range filters, query Atlas Search directly via raw aggregation.

| Approach | When to use |
|---|---|
| **Raw `$search` aggregation** | Full control, range filters, Atlas-only apps — no Scout needed |
| **Laravel Scout** | `Model::search()` convenience, paginate(), cross-driver portability |

## Raw Atlas Search (no Scout required)

Query directly on the model's collection — no separate index or import step:

```php
<?php

use App\Models\Product;

$results = Product::raw(fn ($c) => $c->aggregate([
    ['$search' => [
        'index' => 'default',
        'text'  => ['query' => 'wireless headphones', 'path' => ['name', 'description']],
    ]],
    ['$match'  => ['price' => ['$lte' => 200]]],
    ['$limit'  => 10],
]));
```

## Scout integration

### Installation

```
composer require laravel/scout
php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"
```

### Configuration

```php
// config/scout.php
return [
    'driver' => env('SCOUT_DRIVER', 'mongodb'),

    'mongodb' => [
        'connection' => env('SCOUT_MONGODB_CONNECTION', 'mongodb'),
    ],
];
```

```
SCOUT_DRIVER=mongodb
```

### Searchable model

```php
<?php

namespace App\Models;

use Laravel\Scout\Searchable;
use MongoDB\Laravel\Eloquent\Model;

final class Product extends Model
{
    use Searchable;

    protected $fillable = ['name', 'description', 'price', 'tags'];

    public function toSearchableArray(): array
    {
        return [
            'name'        => $this->name,
            'description' => $this->description,
            'tags'        => $this->tags,
            'price'       => (float) $this->price,
        ];
    }

    public function searchableAs(): string
    {
        // Return the collection name; Scout uses 'scout' as the Atlas Search index name by default.
        return 'products';
    }
}
```

### Creating the Atlas Search index

```php
Schema::connection('mongodb')->create('products', function (Blueprint $c): void {
    $c->searchIndex([
        'mappings' => [
            'dynamic' => false,
            'fields'  => [
                'name'        => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'tags'        => ['type' => 'string'],
                'price'       => ['type' => 'number'],
            ],
        ],
    ], 'scout');
});
```

### Queries

```php
$results = Product::search('wireless headphones')->get();
$page    = Product::search('headphones')->paginate(15);
```

Scout `where()` supports **equality filters only** in the MongoDB engine. Range filters are not supported via Scout:

```php
// WRONG — range filter not supported via Scout
$results = Product::search('headphones')->where('price', '<=', 200)->get();

// CORRECT — use raw aggregation for range filters
$results = Product::raw(fn ($c) => $c->aggregate([
    ['$search' => ['index' => 'scout', 'text' => ['query' => 'headphones', 'path' => 'name']]],
    ['$match'  => ['price' => ['$lte' => 200]]],
]));
```

## Vector search

See `references/vector-search.md` for vector search, auto-embedding, and hybrid search.
