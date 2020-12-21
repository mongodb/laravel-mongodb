# Eloquent

## Extending the base model
This package includes a MongoDB enabled Eloquent class that you can use to define models for corresponding collections.

```php
use Jenssegers\Mongodb\Eloquent\Model;

class Book extends Model
{
    //
}
```

Just like a normal model, the MongoDB model class will know which collection to use based on the model name. For `Book`, the collection `books` will be used.

To change the collection, pass the `$collection` property:

```php
use Jenssegers\Mongodb\Eloquent\Model;

class Book extends Model
{
    protected $collection = 'my_books_collection';
}
```

**NOTE:** MongoDB documents are automatically stored with a unique ID that is stored in the `_id` property. If you wish to use your own ID, substitute the `$primaryKey` property and set it to your own primary key attribute name.

```php
use Jenssegers\Mongodb\Eloquent\Model;

class Book extends Model
{
    protected $primaryKey = 'id';
}

// Mongo will also create _id, but the 'id' property will be used for primary key actions like find().
Book::create(['id' => 1, 'title' => 'The Fault in Our Stars']);
```

Likewise, you may define a `connection` property to override the name of the database connection that should be used when utilizing the model.

```php
use Jenssegers\Mongodb\Eloquent\Model;

class Book extends Model
{
    protected $connection = 'mongodb';
}
```

## Extending the Authenticable base model
This package includes a MongoDB Authenticatable Eloquent class `Jenssegers\Mongodb\Auth\User` that you can use to replace the default Authenticatable class `Illuminate\Foundation\Auth\User` for your `User` model.

```php
use Jenssegers\Mongodb\Auth\User as Authenticatable;

class User extends Authenticatable
{

}
```

## Soft Deletes

When soft deleting a model, it is not actually removed from your database. Instead, a deleted_at timestamp is set on the record.

To enable soft deletes for a model, apply the `Jenssegers\Mongodb\Eloquent\SoftDeletes` Trait to the model:

```php
use Jenssegers\Mongodb\Eloquent\SoftDeletes;

class User extends Model
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];
}
```

For more information check [Laravel Docs about Soft Deleting](http://laravel.com/docs/eloquent#soft-deleting).

## Guarding attributes

When choosing between guarding attributes or marking some as fillable, Taylor Otwell prefers the fillable route.
This is in light of [recent security issues described here](https://blog.laravel.com/security-release-laravel-61835-7240).

Keep in mind guarding still works, but you may experience unexpected behavior.

## Dates

Eloquent allows you to work with Carbon or DateTime objects instead of MongoDate objects. Internally, these dates will be converted to MongoDate objects when saved to the database.

```php
use Jenssegers\Mongodb\Eloquent\Model;

class User extends Model
{
    protected $dates = ['birthday'];
}
```

This allows you to execute queries like this:

```php
$users = User::where(
    'birthday', '>',
    new DateTime('-18 years')
)->get();
```

## Basic Usage

### Retrieving all models

```php
$users = User::all();
```

### Retrieving a record by primary key

```php
$user = User::find('517c43667db388101e00000f');
```

### Where

```php
$posts =
    Post::where('author.name', 'John')
        ->take(10)
        ->get();
```

### OR Statements

```php
$posts =
    Post::where('votes', '>', 0)
        ->orWhere('is_approved', true)
        ->get();
```

### AND statements

```php
$users =
    User::where('age', '>', 18)
        ->where('name', '!=', 'John')
        ->get();
```

### whereIn

```php
$users = User::whereIn('age', [16, 18, 20])->get();
```

When using `whereNotIn` objects will be returned if the field is non-existent. Combine with `whereNotNull('age')` to leave out those documents.

### whereBetween

```php
$posts = Post::whereBetween('votes', [1, 100])->get();
```

### whereNull

```php
$users = User::whereNull('age')->get();
```

### Advanced wheres

```php
$users =
    User::where('name', 'John')
        ->orWhere(function ($query) {
            return $query
                ->where('votes', '>', 100)
                ->where('title', '<>', 'Admin');
        })->get();
```

### orderBy

```php
$users = User::orderBy('age', 'desc')->get();
```

### Offset & Limit (skip & take)

```php
$users =
    User::skip(10)
        ->take(5)
        ->get();
```

### groupBy

Selected columns that are not grouped will be aggregated with the `$last` function.

```php
$users =
    Users::groupBy('title')
        ->get(['title', 'name']);
```

### Distinct

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

### Like

```php
$spamComments = Comment::where('body', 'like', '%spam%')->get();
```

### Aggregation

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

### Incrementing/Decrementing the value of a column

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

## MongoDB-specific operators

**Exists**

Matches documents that have the specified field.

```php
User::where('age', 'exists', true)->get();
```

**All**

Matches arrays that contain all elements specified in the query.

```php
User::where('roles', 'all', ['moderator', 'author'])->get();
```

**Size**

Selects documents if the array field is a specified size.

```php
Post::where('tags', 'size', 3)->get();
```

**Regex**

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

**Type**

Selects documents if a field is of the specified type. For more information check: http://docs.mongodb.org/manual/reference/operator/query/type/#op._S_type

```php
User::where('age', 'type', 2)->get();
```

**Mod**

Performs a modulo operation on the value of a field and selects documents with a specified result.

```php
User::where('age', 'mod', [10, 0])->get();
```

## MongoDB-specific Geo operations

**Near**

```php
$bars = Bar::where('location', 'near', [
    '$geometry' => [
        'type' => 'Point',
        'coordinates' => [
            -0.1367563, // longitude
            51.5100913, // latitude
        ],
    ],
    '$maxDistance' => 50,
])->get();
```

**GeoWithin**

```php
$bars = Bar::where('location', 'geoWithin', [
    '$geometry' => [
        'type' => 'Polygon',
        'coordinates' => [
            [
                [-0.1450383, 51.5069158],
                [-0.1367563, 51.5100913],
                [-0.1270247, 51.5013233],
                [-0.1450383, 51.5069158],
            ],
        ],
    ],
])->get();
```

**GeoIntersects**

```php
$bars = Bar::where('location', 'geoIntersects', [
    '$geometry' => [
        'type' => 'LineString',
        'coordinates' => [
            [-0.144044, 51.515215],
            [-0.129545, 51.507864],
        ],
    ],
])->get();
```
## Inserts, updates and deletes

Inserting, updating and deleting records works just like the original Eloquent. Please check [Laravel Docs' Eloquent section](https://laravel.com/docs/6.x/eloquent).

Here, only the MongoDB-specific operations are specified.

## MongoDB specific operations

### Raw Expressions

These expressions will be injected directly into the query.

```php
User::whereRaw([
    'age' => ['$gt' => 30, '$lt' => 40],
])->get();
```

You can also perform raw expressions on the internal MongoCollection object. If this is executed on the model class, it will return a collection of models.

If this is executed on the query builder, it will return the original response.

### Cursor timeout

To prevent `MongoCursorTimeout` exceptions, you can manually set a timeout value that will be applied to the cursor:

```php
DB::collection('users')->timeout(-1)->get();
```

### Upsert

Update or insert a document. Additional options for the update method are passed directly to the native update method.

```php
// Query Builder
DB::collection('users')
    ->where('name', 'John')
    ->update($data, ['upsert' => true]);

// Eloquent
$user->update($data, ['upsert' => true]);
```

### Projections

You can apply projections to your queries using the `project` method.

```php
DB::collection('items')
    ->project(['tags' => ['$slice' => 1]])
    ->get();

DB::collection('items')
    ->project(['tags' => ['$slice' => [3, 7]]])
    ->get();
```

### Projections with Pagination

```php
$limit = 25;
$projections = ['id', 'name'];

DB::collection('items')
    ->paginate($limit, $projections);
```

### Push

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

### Pull

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

### Unset

Remove one or more fields from a document.

```php
DB::collection('users')
    ->where('name', 'John')
    ->unset('note');

$user->unset('note');
```