# Basic Usage

The database driver plugs right into the original query builder.

When using MongoDB connections, you will be able to build fluent queries to perform database operations.

For your convenience, there is a `collection` alias for `table` as well as some additional MongoDB specific operators/operations.

```php
$books = DB::collection('books')->get();

$hungerGames =
    DB::collection('books')
        ->where('name', 'Hunger Games')
        ->first();
```

If you are familiar with [Eloquent Queries](http://laravel.com/docs/queries), there is the same functionality.