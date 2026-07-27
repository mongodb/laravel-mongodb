# Vector Search

Atlas Vector Search lets you query documents by semantic similarity using embedding vectors stored alongside your data.

## Two approaches

| Approach | When to use |
|---|---|
| **Auto-embedding** | Documents are plain text fields; Atlas generates and maintains vectors automatically — no PHP embedding code needed |
| **Manual embedding** | You control the embedding model, need custom dimensions, or process non-text data (images, audio) |

## Auto-embedding (recommended for text)

Atlas can generate and maintain embeddings automatically via the `autoEmbed` index type. No PHP code is needed to generate or store vectors — Atlas embeds on insert and update.

### 1. Create the Vector Search index with auto-embedding

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mongodb')->table('products', function ($collection): void {
            $collection->vectorSearchIndex([
                'fields' => [
                    [
                        'type'     => 'autoEmbed',
                        'modality' => 'text',
                        'path'     => 'description',   // source text field
                        'model'    => 'voyage-4',
                    ],
                    ['type' => 'filter', 'path' => 'category'],
                ],
            ], 'products_vector');
        });
    }
};
```

Available models: `voyage-4`, `voyage-4-large`, and others from MongoDB-supported providers.
Optional fields: `numDimensions`, `quantization` (`float`|`scalar`|`binary`|`binaryNoRescore`), `similarity` (`euclidean`|`cosine`|`dotProduct`), `indexingMethod` (`flat`|`hnsw`), `hnswOptions`.

### 2. Insert documents normally — no embedding step

```php
<?php

use App\Models\Product;

// Atlas generates the embedding for 'description' automatically on insert
Product::create([
    'name'        => 'Wireless headphones',
    'description' => 'Over-ear noise-cancelling headphones with 30h battery',
    'price'       => 149.99,
]);
```

### 3. Query using text (Atlas auto-embeds the query)

```php
<?php

use App\Models\Product;

$results = Product::raw(fn ($c) => $c->aggregate([
    ['$vectorSearch' => [
        'index'         => 'products_vector',
        'path'          => 'description',
        'queryText'     => 'noise-cancelling headphones for travel',
        'numCandidates' => 100,
        'limit'         => 10,
    ]],
    ['$project' => [
        'name'        => 1,
        'description' => 1,
        'price'       => 1,
        'score'       => ['$meta' => 'vectorSearchScore'],
    ]],
]));
```

Use `queryText` (string) when the index uses `autoEmbed`. Use `queryVector` (float array) when providing vectors manually.

## Manual embedding

Use when you generate vectors yourself (Laravel AI SDK, OpenAI PHP, etc.) and store them in the document.

Always show all three steps: **create the index**, **store the embedding on write**, **query by vector**.

### 1. Create the Vector Search index

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mongodb')->table('products', function ($collection): void {
            $collection->vectorSearchIndex([
                'fields' => [
                    [
                        'type'          => 'vector',
                        'path'          => 'embedding',
                        'numDimensions' => 1536,
                        'similarity'    => 'cosine',
                    ],
                    ['type' => 'filter', 'path' => 'category'],
                ],
            ], 'products_vector');
        });
    }
};
```

### 2. Store the embedding on write

```php
<?php

use App\Models\Product;
use OpenAI\Laravel\Facades\OpenAI;

$response = OpenAI::embeddings()->create([
    'model' => 'text-embedding-3-small',
    'input' => $description,
]);

Product::create([
    'name'        => 'Wireless headphones',
    'description' => $description,
    'price'       => 149.99,
    'embedding'   => $response->embeddings[0]->embedding,
]);
```

### 3. Query by vector

```php
<?php

use App\Models\Product;
use OpenAI\Laravel\Facades\OpenAI;

$queryVector = OpenAI::embeddings()->create([
    'model' => 'text-embedding-3-small',
    'input' => $userQuery,
])->embeddings[0]->embedding;

$results = Product::vectorSearch(
    index: 'products_vector',
    path: 'embedding',
    queryVector: $queryVector,
    numCandidates: 100,
    limit: 10,
);
```

## Hybrid search (text + vector)

Combine `$search` and `$vectorSearch` with `$rankFusion`:

```php
<?php

use App\Models\Product;

$results = Product::raw(fn ($c) => $c->aggregate([
    ['$rankFusion' => [
        'input' => [
            'pipelines' => [
                'fullText' => [
                    ['$search' => [
                        'index' => 'default',
                        'text'  => ['query' => $query, 'path' => 'description'],
                    ]],
                ],
                'semantic' => [
                    ['$vectorSearch' => [
                        'index'         => 'products_vector',
                        'path'          => 'embedding',
                        'queryVector'   => $queryVector,
                        'numCandidates' => 100,
                        'limit'         => 20,
                    ]],
                ],
            ],
        ],
        'combination' => ['weights' => ['fullText' => 0.4, 'semantic' => 0.6]],
    ]],
    ['$limit' => 10],
]));
```

## Requirements

- Atlas M10+ cluster (Vector Search is not available on free/shared tiers)
- Auto-embedding requires a configured embedding provider API key in Atlas
- Allow a few minutes after `php artisan migrate` for Atlas to build the index before querying
