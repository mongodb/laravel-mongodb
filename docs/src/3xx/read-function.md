---
title: READ / GET DATA
sidebarDepth: 3
---

This are some basic way to retrieve or read data.

## Retrieving all models
This will retrieve all data, `Note: If you have a lot thousands of data, this is not recommended way`
```php
$users = User::all();
```

## Retrieving a record by primary key
This will retrieve specific data by finding specific id.
```php
$user = User::find('517c43667db388101e00000f');
```

## Where
We can also get data that we want using where.
```php
$posts =
    Person::where('type', 'normal')
        ->take(10)
        ->get();


// if embbed data
$user = User::find('517c43667db388101e00000f')
$user->books()->where('title','The Lion and the Tiger')->take(10)->get();
```

## OR Statements
We can also use OR to get data if one or more condition is met.
```php
$posts =
    Post::where('votes', '>', 0)
        ->orWhere('is_approved', true)
        ->get();
```

## AND statements
Using statement is by using where function, this will get data if all condition is met.
```php
$users =
    User::where('age', '>', 18)
        ->where('name', '!=', 'John')
        ->get();
```

## whereIn
This will fetch data if one of the choices exist.
```php
$users = User::whereIn('age', [16, 18, 20])->get();
```

When using `whereNotIn` objects will be returned if the field is non-existent. Combine with `whereNotNull('age')` to leave out those documents.

## whereBetween
We can also use inbetween `(mostly numbers and dates)`. To fetch data that exist in between the condition.
```php
$posts = Post::whereBetween('votes', [1, 100])->get();
```

## whereNull
Fetch data where in the collumn is not null.
```php
$users = User::whereNull('age')->get();
```

## Advanced wheres
We can also add function inside where or orWhere function to be able to create methods inside it.
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
OrderBy is used to order the data being fethced. 
```php
// desc or asc
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
