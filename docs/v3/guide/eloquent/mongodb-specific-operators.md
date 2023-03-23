# MongoDB-specific operators

## Exists

Matches documents that have the specified field.

```php
User::where('age', 'exists', true)->get();
```

## All

Matches arrays that contain all elements specified in the query.

```php
User::where('roles', 'all', ['moderator', 'author'])->get();
```

## Size

Selects documents if the array field is a specified size.

```php
Post::where('tags', 'size', 3)->get();
```

## Regex

Selects documents where values match a specified regular expression.

```php
use MongoDB\BSON\Regex;

User::where('name', 'regex', new Regex('.*doe', 'i'))->get();
```

**NOTE:** you can also use the Laravel regexp operations. These are a bit more flexible and will automatically convert your regular expression string to a `MongoDB\BSON\Regex` object.

```php
User::where('name', 'regexp', '/.*doe/i')->get();
```

The inverse of regexp:

```php
User::where('name', 'not regexp', '/.*doe/i')->get();
```

## Type

Selects documents if a field is of the specified type. For more information check: http://docs.mongodb.org/manual/reference/operator/query/type/#op._S_type

```php
User::where('age', 'type', 2)->get();
```

## Mod

Performs a modulo operation on the value of a field and selects documents with a specified result.

```php
User::where('age', 'mod', [10, 0])->get();
```