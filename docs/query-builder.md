Query Builder
=============

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

Available operations
--------------------

**Retrieving all models**

```php
$users = User::all();
```

**Retrieving a record by primary key**

```php
$user = User::find('517c43667db388101e00000f');
```

**Where**

```php
$posts =
    Post::where('author.name', 'John')
        ->take(10)
        ->get();
```

**OR Statements**

```php
$posts =
    Post::where('votes', '>', 0)
        ->orWhere('is_approved', true)
        ->get();
```

**AND statements**

```php
$users =
    User::where('age', '>', 18)
        ->where('name', '!=', 'John')
        ->get();
```

**NOT statements**

```php
$users = User::whereNot('age', '>', 18)->get();
```

**whereIn**

```php
$users = User::whereIn('age', [16, 18, 20])->get();
```

When using `whereNotIn` objects will be returned if the field is non-existent. Combine with `whereNotNull('age')` to leave out those documents.

**whereBetween**

```php
$posts = Post::whereBetween('votes', [1, 100])->get();
```

**whereNull**

```php
$users = User::whereNull('age')->get();
```

**whereDate**

```php
$users = User::whereDate('birthday', '2021-5-12')->get();
```

The usage is the same as `whereMonth` / `whereDay` / `whereYear` / `whereTime`

**Advanced wheres**

```php
$users =
    User::where('name', 'John')
        ->orWhere(function ($query) {
            return $query
                ->where('votes', '>', 100)
                ->where('title', '<>', 'Admin');
        })->get();
```

**orderBy**

```php
$users = User::orderBy('age', 'desc')->get();
```

**Offset & Limit (skip & take)**

```php
$users =
    User::skip(10)
        ->take(5)
        ->get();
```

**groupBy**

Selected columns that are not grouped will be aggregated with the `$last` function.

```php
$users =
    Users::groupBy('title')
        ->get(['title', 'name']);
```

**Distinct**

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

**Like**

```php
$spamComments = Comment::where('body', 'like', '%spam%')->get();
```

**Aggregation**

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

### MongoDB-specific operators

In addition to the Laravel Eloquent operators, all available MongoDB query operators can be used with `where`:

```php
User::where($fieldName, $operator, $value)->get();
```

It generates the following MongoDB filter:
```ts
{ $fieldName: { $operator: $value } }
```

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

**NOTE:** you can also use the Laravel regexp operations. These will automatically convert your regular expression string to a `MongoDB\BSON\Regex` object.

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

### MongoDB-specific Geo operations

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

**GeoNear**

You are able to make a `geoNear` query on mongoDB.
You don't need to specify the automatic fields on the model.
The returned instance is a collection. So you're able to make the [Collection](https://laravel.com/docs/9.x/collections) operations.
Just make sure that your model has a `location` field, and a [2ndSphereIndex](https://www.mongodb.com/docs/manual/core/2dsphere).
The data in the `location` field must be saved as [GeoJSON](https://www.mongodb.com/docs/manual/reference/geojson/).
The `location` points must be saved as [WGS84](https://www.mongodb.com/docs/manual/reference/glossary/#std-term-WGS84) reference system for geometry calculation. That means, basically, you need to save `longitude and latitude`, in that order specifically, and to find near with calculated distance, you `need to do the same way`.

```
Bar::find("63a0cd574d08564f330ceae2")->update(
    [
        'location' => [
            'type' => 'Point',
            'coordinates' => [
                -0.1367563,
                51.5100913
            ]
        ]
    ]
);
$bars = Bar::raw(function ($collection) {
    return $collection->aggregate([
        [
            '$geoNear' => [
                "near" => [ "type" =>  "Point", "coordinates" =>  [-0.132239, 51.511874] ],
                "distanceField" =>  "dist.calculated",
                "minDistance" =>  0,
                "maxDistance" =>  6000,
                "includeLocs" =>  "dist.location",
                "spherical" =>  true,
            ]
        ]
    ]);
});
```

### Inserts, updates and deletes

Inserting, updating and deleting records works just like the original Eloquent. Please check [Laravel Docs' Eloquent section](https://laravel.com/docs/6.x/eloquent).

Here, only the MongoDB-specific operations are specified.

### MongoDB specific operations

**Raw Expressions**

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

**Cursor timeout**

To prevent `MongoCursorTimeout` exceptions, you can manually set a timeout value that will be applied to the cursor:

```php
DB::collection('users')->timeout(-1)->get();
```

**Upsert**

Update or insert a document. Additional options for the update method are passed directly to the native update method.

```php
// Query Builder
DB::collection('users')
    ->where('name', 'John')
    ->update($data, ['upsert' => true]);

// Eloquent
$user->update($data, ['upsert' => true]);
```

**Projections**

You can apply projections to your queries using the `project` method.

```php
DB::collection('items')
    ->project(['tags' => ['$slice' => 1]])
    ->get();

DB::collection('items')
    ->project(['tags' => ['$slice' => [3, 7]]])
    ->get();
```

**Projections with Pagination**

```php
$limit = 25;
$projections = ['id', 'name'];

DB::collection('items')
    ->paginate($limit, $projections);
```

**Push**

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

**Pull**

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

**Unset**

Remove one or more fields from a document.

```php
DB::collection('users')
    ->where('name', 'John')
    ->unset('note');

$user->unset('note');

$user->save();
```

Using the native `unset` on models will work as well:

```php
unset($user['note']);
unset($user->node);
```
