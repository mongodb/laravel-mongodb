# MongoDB specific operations

## Raw Expressions

These expressions will be injected directly into the query.

```php
User::whereRaw([
    'age' => ['$gt' => 30, '$lt' => 40],
])->get();

User::whereRaw([
    '$where' => '/.*123.*/.test(this.field)',
])->get();

User::whereRaw([
    '$where' => '/.*123.*/.test(this["hyphenated-field"])',
])->get();
```

You can also perform raw expressions on the internal MongoCollection object. If this is executed on the model class, it will return a collection of models.

If this is executed on the query builder, it will return the original response.

## Cursor timeout

To prevent `MongoCursorTimeout` exceptions, you can manually set a timeout value that will be applied to the cursor:

```php
DB::collection('users')->timeout(-1)->get();
```

## Upsert

Update or insert a document. Additional options for the update method are passed directly to the native update method.

```php
// Query Builder
DB::collection('users')
    ->where('name', 'John')
    ->update($data, ['upsert' => true]);

// Eloquent
$user->update($data, ['upsert' => true]);
```

## Projections

You can apply projections to your queries using the `project` method.

```php
DB::collection('items')
    ->project(['tags' => ['$slice' => 1]])
    ->get();

DB::collection('items')
    ->project(['tags' => ['$slice' => [3, 7]]])
    ->get();
```

## Projections with Pagination

```php
$limit = 25;
$projections = ['id', 'name'];

DB::collection('items')
    ->paginate($limit, $projections);
```

## Push

Add items to an array.

```php
DB::collection('users')
    ->where('name', 'John')
    ->push('items', 'boots');

$user->push('items', 'boots');
```

```php
DB::collection('users')
    ->where('name', 'John')
    ->push('messages', [
        'from' => 'Jane Doe',
        'message' => 'Hi John',
    ]);

$user->push('messages', [
    'from' => 'Jane Doe',
    'message' => 'Hi John',
]);
```

If you **DON'T** want duplicate items, set the third parameter to `true`:

```php
DB::collection('users')
    ->where('name', 'John')
    ->push('items', 'boots', true);

$user->push('items', 'boots', true);
```

## Pull

Remove an item from an array.

```php
DB::collection('users')
    ->where('name', 'John')
    ->pull('items', 'boots');

$user->pull('items', 'boots');
```

```php
DB::collection('users')
    ->where('name', 'John')
    ->pull('messages', [
        'from' => 'Jane Doe',
        'message' => 'Hi John',
    ]);

$user->pull('messages', [
    'from' => 'Jane Doe',
    'message' => 'Hi John',
]);
```

## Unset

Remove one or more fields from a document.

```php
DB::collection('users')
    ->where('name', 'John')
    ->unset('note');

$user->unset('note');
```