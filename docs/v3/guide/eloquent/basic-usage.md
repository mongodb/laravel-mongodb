# Basic Usage

## Retrieving all models

```php
$users = User::all();
```

## Retrieving a record by primary key

```php
$user = User::find('517c43667db388101e00000f');
```

## Where

```php
$posts =
    Post::where('author.name', 'John')
        ->take(10)
        ->get();
```

## OR Statements

```php
$posts =
    Post::where('votes', '>', 0)
        ->orWhere('is_approved', true)
        ->get();
```

## AND statements

```php
$users =
    User::where('age', '>', 18)
        ->where('name', '!=', 'John')
        ->get();
```

## whereIn

```php
$users = User::whereIn('age', [16, 18, 20])->get();
```

When using `whereNotIn` objects will be returned if the field is non-existent. Combine with `whereNotNull('age')` to leave out those documents.

## whereBetween

```php
$posts = Post::whereBetween('votes', [1, 100])->get();
```

## whereNull

```php
$users = User::whereNull('age')->get();
```

## whereDate

```php
$users = User::whereDate('birthday', '2021-5-12')->get();
```

The usage is the same as `whereMonth` / `whereDay` / `whereYear` / `whereTime`

## Advanced wheres

```php
$users =
    User::where('name', 'John')
        ->orWhere(function ($query) {
            return $query
                ->where('votes', '>', 100)
                ->where('title', '<>', 'Admin');
        })->get();
```

## orderBy

```php
$users = User::orderBy('age', 'desc')->get();
```

## Offset & Limit (skip & take)

```php
$users =
    User::skip(10)
        ->take(5)
        ->get();
```

## groupBy

Selected columns that are not grouped will be aggregated with the `$last` function.

```php
$users =
    Users::groupBy('title')
        ->get(['title', 'name']);
```

## Distinct

Distinct requires a field for which to return the distinct values.

```php
$users = User::distinct()->get(['name']);

// Equivalent to:
$users = User::distinct('name')->get();
```

Distinct can be combined with **where**:

```php
$users =
    User::where('active', true)
        ->distinct('name')
        ->get();
```

## Like

```php
$spamComments = Comment::where('body', 'like', '%spam%')->get();
```

## Aggregation

**Aggregations are only available for MongoDB versions greater than 2.2.x**

```php
$total = Product::count();
$price = Product::max('price');
$price = Product::min('price');
$price = Product::avg('price');
$total = Product::sum('price');
```

Aggregations can be combined with **where**:

```php
$sold = Orders::where('sold', true)->sum('price');
```

Aggregations can be also used on sub-documents:

```php
$total = Order::max('suborder.price');
```

**NOTE**: This aggregation only works with single sub-documents (like `EmbedsOne`) not subdocument arrays (like `EmbedsMany`).

## Incrementing/Decrementing

**Incrementing/Decrementing the value of a column**

Perform increments or decrements (default 1) on specified attributes:

```php
Cat::where('name', 'Kitty')->increment('age');

Car::where('name', 'Toyota')->decrement('weight', 50);
```

The number of updated objects is returned:

```php
$count = User::increment('age');
```

You may also specify additional columns to update:

```php
Cat::where('age', 3)
    ->increment('age', 1, ['group' => 'Kitty Club']);

Car::where('weight', 300)
    ->decrement('weight', 100, ['latest_change' => 'carbon fiber']);
```