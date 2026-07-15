# Query Builder

## Unsupported helpers and replacements

| Standard Eloquent | Status | MongoDB replacement |
|---|---|---|
| `toSql()` / `toRawSql()` | unsupported | `->dump()` / `->dd()` / `->toMql()` |
| `withCount()` / `withAvg()` / `withSum()` | unsupported | aggregation `$lookup` + `$size` / `$avg` / `$sum` |
| `groupByRaw()` / `orderByRaw()` / `havingRaw()` | unsupported | aggregation `$group` / `$sort` |
| `whereFulltext()` | unsupported | Atlas Search `$search` stage |
| `union()` | unsupported | aggregation `$unionWith` |
| `whereColumn()` | unsupported | aggregation `$expr` + `$eq` |
| `inRandomOrder()` | unsupported | aggregation `$sample` |
| SQL `JOIN` | unsupported | aggregation `$lookup` |

## `distinct()` returns a Collection, not scalars

**Common mistake:** `Movie::distinct('genre')->get()` returns a Collection of model objects, **not** an array of scalar strings. Always use `->distinct()->pluck('field')` to get scalar values.

```php
// WRONG — returns Collection of model objects, NOT ['Action', 'Comedy', 'Drama']
$genres = Movie::distinct('genre')->get();

// CORRECT — returns a flat Collection of scalar strings
$genres = Movie::distinct()->pluck('genre');

// CORRECT — explicit aggregation when you need sorting or more control
$genres = Movie::raw(fn ($c) => $c->aggregate([
    ['$group' => ['_id' => '$genre']],
    ['$sort'  => ['_id' => 1]],
]))->pluck('_id');
```

Do **not** use `Movie::raw(fn ($c) => $c->distinct('field'))` via Eloquent if you expect a Collection — use `->distinct()->pluck()` instead.

## Replacing `withCount`

`withCount()`, `withAvg()`, and `withSum()` are **not supported** on MongoDB models — they silently return wrong results or throw. Replace with a `$lookup` + `$size` / `$avg` / `$sum` aggregation pipeline.

```php
// WRONG — withCount is not supported on MongoDB models
$posts = Post::withCount('comments')->get();

// CORRECT
$posts = Post::raw(fn ($c) => $c->aggregate([
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

## Inspecting the generated query

```php
// WRONG — toSql() does not exist
$sql = User::where('active', true)->toSql();

// CORRECT
User::query()->where('active', true)->dump();
dd(User::query()->where('active', true)->toMql());
```

## Random sampling

```php
// WRONG
$movies = Movie::inRandomOrder()->take(5)->get();

// CORRECT
$movies = Movie::raw(fn ($c) => $c->aggregate([
    ['$sample' => ['size' => 5]],
]));
```

## `whereColumn` replacement

```php
// WRONG
User::whereColumn('created_at', 'updated_at')->get();

// CORRECT
User::whereRaw(['$expr' => ['$eq' => ['$created_at', '$updated_at']]])->get();
```

## MongoDB-specific operators

```php
Post::where('tags', 'all', ['mongo', 'laravel'])->get();   // $all
Post::where('views', '>=', 100)->get();
Post::whereBetween('year', [2000, 2010])->get();
Post::where('metadata.draft', true)->get();                // dotted path into sub-doc
```

## Raw aggregation entry point

```php
$result = Post::raw(fn ($collection) => $collection->aggregate([
    ['$match'  => ['published' => true]],
    ['$group'  => ['_id' => '$author_id', 'total' => ['$sum' => 1]]],
    ['$sort'   => ['total' => -1]],
    ['$limit'  => 10],
]));
```
