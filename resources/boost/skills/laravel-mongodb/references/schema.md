# Schema and Migrations

MongoDB is schema-flexible — migrations exist almost exclusively to manage indexes and seed data.

## Migration template

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        Schema::connection('mongodb')->create('posts', function (Blueprint $collection): void {
            $collection->index('author_id');
            $collection->unique('slug');
            $collection->index(['created_at' => -1]);                        // descending
            $collection->index(['author_id' => 1, 'created_at' => -1]);     // compound
            $collection->geospatial('location', '2dsphere');
            $collection->expire('expires_at', 0);                           // TTL index
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->drop('posts');
    }
};
```

```
php artisan migrate --database=mongodb
```

## Blueprint methods

| Method | Purpose |
|---|---|
| `->index($field)` | single-field ascending index |
| `->index(['f' => 1, 'g' => -1])` | compound / sort-aware index |
| `->unique($field)` | unique index |
| `->sparse($field)` | sparse index |
| `->expire($field, $seconds)` | TTL index — auto-delete docs after N seconds |
| `->geospatial($field, '2dsphere')` | geo index |
| `->dropIndex($name)` | remove a regular index |
| `->dropIndexIfExists($name)` | remove if exists (idempotent migrations) |
| `->searchIndex($definition)` | create Atlas Search index |
| `->vectorSearchIndex($definition, $name)` | create Atlas Vector Search index |
| `->dropSearchIndex($name)` | drop Atlas Search or Vector Search index |

## Atlas Search and Vector Search indexes

```php
$collection->searchIndex([
    'mappings' => ['dynamic' => true],
]);

$collection->vectorSearchIndex([
    'fields' => [['type' => 'vector', 'path' => 'embedding', 'numDimensions' => 1536, 'similarity' => 'cosine']],
], 'products_vector');
```

Can also be managed via the Atlas UI, Atlas Admin API, or MongoDB MCP server.

## No column definitions

```php
// WRONG — column methods silently do nothing in MongoDB
$collection->string('title');
$collection->integer('views');

// CORRECT — schema is enforced at the model level (casts, validation rules)
// For server-side enforcement, use $jsonSchema validator.
```
