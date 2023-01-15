Laravel MongoDB
===============

[![Latest Stable Version](http://img.shields.io/github/release/jenssegers/laravel-mongodb.svg)](https://packagist.org/packages/jenssegers/mongodb)
[![Total Downloads](http://img.shields.io/packagist/dm/jenssegers/mongodb.svg)](https://packagist.org/packages/jenssegers/mongodb)
[![Build Status](https://img.shields.io/github/workflow/status/jenssegers/laravel-mongodb/CI)](https://github.com/jenssegers/laravel-mongodb/actions)
[![codecov](https://codecov.io/gh/jenssegers/laravel-mongodb/branch/master/graph/badge.svg)](https://codecov.io/gh/jenssegers/laravel-mongodb/branch/master)
[![Donate](https://img.shields.io/badge/donate-paypal-blue.svg)](https://www.paypal.me/jenssegers)

This package adds functionalities to the Eloquent model and Query builder for MongoDB, using the original Laravel API. *This library extends the original Laravel classes, so it uses exactly the same methods.*

- [Laravel MongoDB](#laravel-mongodb)
    - [Installation](#installation)
        - [Laravel version Compatibility](#laravel-version-compatibility)
        - [Laravel](#laravel)
        - [Lumen](#lumen)
        - [Non-Laravel projects](#non-laravel-projects)
    - [Testing](#testing)
    - [Database Testing](#database-testing)
    - [Configuration](#configuration)
    - [Eloquent](#eloquent)
        - [Extending the base model](#extending-the-base-model)
        - [Extending the Authenticable base model](#extending-the-authenticable-base-model)
        - [Soft Deletes](#soft-deletes)
        - [Guarding attributes](#guarding-attributes)
        - [Dates](#dates)
        - [Basic Usage](#basic-usage)
        - [MongoDB-specific operators](#mongodb-specific-operators)
        - [MongoDB-specific Geo operations](#mongodb-specific-geo-operations)
        - [Inserts, updates and deletes](#inserts-updates-and-deletes)
        - [MongoDB specific operations](#mongodb-specific-operations)
    - [Relationships](#relationships)
        - [Basic Usage](#basic-usage-1)
        - [belongsToMany and pivots](#belongstomany-and-pivots)
        - [EmbedsMany Relationship](#embedsmany-relationship)
        - [EmbedsOne Relationship](#embedsone-relationship)
    - [Query Builder](#query-builder)
        - [Basic Usage](#basic-usage-2)
        - [Available operations](#available-operations)
    - [Transactions](#transactions)
    - [Schema](#schema)
        - [Basic Usage](#basic-usage-3)
        - [Geospatial indexes](#geospatial-indexes)
    - [Extending](#extending)
        - [Cross-Database Relationships](#cross-database-relationships)
        - [Authentication](#authentication)
        - [Queues](#queues)
            - [Laravel specific](#laravel-specific)
            - [Lumen specific](#lumen-specific)
    - [Upgrading](#upgrading)
        - [Upgrading from version 2 to 3](#upgrading-from-version-2-to-3)
    - [Security contact information](#security-contact-information)

Installation
------------

Make sure you have the MongoDB PHP driver installed. You can find installation instructions at http://php.net/manual/en/mongodb.installation.php

### Laravel version Compatibility

| Laravel | Package        | Maintained         |
| :------ | :------------- | :----------------- |
| 9.x     | 3.9.x          | :white_check_mark: |
| 8.x     | 3.8.x          | :white_check_mark: |
| 7.x     | 3.7.x          | :x:                |
| 6.x     | 3.6.x          | :x:                |
| 5.8.x   | 3.5.x          | :x:                |
| 5.7.x   | 3.4.x          | :x:                |
| 5.6.x   | 3.4.x          | :x:                |
| 5.5.x   | 3.3.x          | :x:                |
| 5.4.x   | 3.2.x          | :x:                |
| 5.3.x   | 3.1.x or 3.2.x | :x:                |
| 5.2.x   | 2.3.x or 3.0.x | :x:                |
| 5.1.x   | 2.2.x or 3.0.x | :x:                |
| 5.0.x   | 2.1.x          | :x:                |
| 4.2.x   | 2.0.x          | :x:                |

Install the package via Composer:

```bash
$ composer require jenssegers/mongodb
```

### Laravel

In case your Laravel version does NOT autoload the packages, add the service provider to `config/app.php`:

```php
Jenssegers\Mongodb\MongodbServiceProvider::class,
```

### Lumen

For usage with [Lumen](http://lumen.laravel.com), add the service provider in `bootstrap/app.php`. In this file, you will also need to enable Eloquent. You must however ensure that your call to `$app->withEloquent();` is **below** where you have registered the `MongodbServiceProvider`:

```php
$app->register(Jenssegers\Mongodb\MongodbServiceProvider::class);

$app->withEloquent();
```

The service provider will register a MongoDB database extension with the original database manager. There is no need to register additional facades or objects.

When using MongoDB connections, Laravel will automatically provide you with the corresponding MongoDB objects.

### Non-Laravel projects

For usage outside Laravel, check out the [Capsule manager](https://github.com/illuminate/database/blob/master/README.md) and add:

```php
$capsule->getDatabaseManager()->extend('mongodb', function($config, $name) {
    $config['name'] = $name;

    return new Jenssegers\Mongodb\Connection($config);
});
```

Testing
-------

To run the test for this package, run:

```
docker-compose up
```

Database Testing
-------

To reset the database after each test, add:

```php
use Illuminate\Foundation\Testing\DatabaseMigrations;
```

Also inside each test classes, add:

```php
use DatabaseMigrations;
```

Keep in mind that these traits are not yet supported:

-   `use Database Transactions;`
-   `use RefreshDatabase;`

Configuration
-------------

To configure a new MongoDB connection, add a new connection entry to `config/database.php`:

```php
'mongodb' => [
    'driver' => 'mongodb',
    'dsn' => env('DB_DSN'),
    'database' => env('DB_DATABASE', 'homestead'),
],
```

The `dsn` key contains the connection string used to connect to your MongoDB deployment. The format and available options are documented in the [MongoDB documentation](https://docs.mongodb.com/manual/reference/connection-string/).

Instead of using a connection string, you can also use the `host` and `port` configuration options to have the connection string created for you.

```php
'mongodb' => [
    'driver' => 'mongodb',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', 27017),
    'database' => env('DB_DATABASE', 'homestead'),
    'username' => env('DB_USERNAME', 'homestead'),
    'password' => env('DB_PASSWORD', 'secret'),
    'options' => [
        'appname' => 'homestead',
    ],
],
```

The `options` key in the connection configuration corresponds to the [`uriOptions` parameter](https://www.php.net/manual/en/mongodb-driver-manager.construct.php#mongodb-driver-manager.construct-urioptions).

Eloquent
--------

### Extending the base model

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

// MongoDB will also create _id, but the 'id' property will be used for primary key actions like find().
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

### Extending the Authenticatable base model

This package includes a MongoDB Authenticatable Eloquent class `Jenssegers\Mongodb\Auth\User` that you can use to replace the default Authenticatable class `Illuminate\Foundation\Auth\User` for your `User` model.

```php
use Jenssegers\Mongodb\Auth\User as Authenticatable;

class User extends Authenticatable
{

}
```

### Soft Deletes

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

### Guarding attributes

When choosing between guarding attributes or marking some as fillable, Taylor Otwell prefers the fillable route.
This is in light of [recent security issues described here](https://blog.laravel.com/security-release-laravel-61835-7240).

Keep in mind guarding still works, but you may experience unexpected behavior.

### Dates

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

### Basic Usage

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
```

Relationships
-------------

### Basic Usage

The only available relationships are:

-   hasOne
-   hasMany
-   belongsTo
-   belongsToMany

The MongoDB-specific relationships are:

-   embedsOne
-   embedsMany

Here is a small example:

```php
use Jenssegers\Mongodb\Eloquent\Model;

class User extends Model
{
    public function items()
    {
        return $this->hasMany(Item::class);
    }
}
```

The inverse relation of `hasMany` is `belongsTo`:

```php
use Jenssegers\Mongodb\Eloquent\Model;

class Item extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

### belongsToMany and pivots

The belongsToMany relation will not use a pivot "table" but will push id's to a __related_ids__ attribute instead. This makes the second parameter for the belongsToMany method useless.

If you want to define custom keys for your relation, set it to `null`:

```php
use Jenssegers\Mongodb\Eloquent\Model;

class User extends Model
{
    public function groups()
    {
        return $this->belongsToMany(
            Group::class, null, 'user_ids', 'group_ids'
        );
    }
}
```

### EmbedsMany Relationship

If you want to embed models, rather than referencing them, you can use the `embedsMany` relation. This relation is similar to the `hasMany` relation but embeds the models inside the parent object.

**REMEMBER**: These relations return Eloquent collections, they don't return query builder objects!

```php
use Jenssegers\Mongodb\Eloquent\Model;

class User extends Model
{
    public function books()
    {
        return $this->embedsMany(Book::class);
    }
}
```

You can access the embedded models through the dynamic property:

```php
$user = User::first();

foreach ($user->books as $book) {
    //
}
```

The inverse relation is auto*magically* available. You don't need to define this reverse relation.

```php
$book = Book::first();

$user = $book->user;
```

Inserting and updating embedded models works similar to the `hasMany` relation:

```php
$book = $user->books()->save(
    new Book(['title' => 'A Game of Thrones'])
);

// or
$book =
    $user->books()
         ->create(['title' => 'A Game of Thrones']);
```

You can update embedded models using their `save` method (available since release 2.0.0):

```php
$book = $user->books()->first();

$book->title = 'A Game of Thrones';
$book->save();
```

You can remove an embedded model by using the `destroy` method on the relation, or the `delete` method on the model (available since release 2.0.0):

```php
$book->delete();

// Similar operation
$user->books()->destroy($book);
```

If you want to add or remove an embedded model, without touching the database, you can use the `associate` and `dissociate` methods.

To eventually write the changes to the database, save the parent object:

```php
$user->books()->associate($book);
$user->save();
```

Like other relations, embedsMany assumes the local key of the relationship based on the model name. You can override the default local key by passing a second argument to the embedsMany method:

```php
use Jenssegers\Mongodb\Eloquent\Model;

class User extends Model
{
    public function books()
    {
        return $this->embedsMany(Book::class, 'local_key');
    }
}
```

Embedded relations will return a Collection of embedded items instead of a query builder. Check out the available operations here: https://laravel.com/docs/master/collections

### EmbedsOne Relationship

The embedsOne relation is similar to the embedsMany relation, but only embeds a single model.

```php
use Jenssegers\Mongodb\Eloquent\Model;

class Book extends Model
{
    public function author()
    {
        return $this->embedsOne(Author::class);
    }
}
```

You can access the embedded models through the dynamic property:

```php
$book = Book::first();
$author = $book->author;
```

Inserting and updating embedded models works similar to the `hasOne` relation:

```php
$author = $book->author()->save(
    new Author(['name' => 'John Doe'])
);

// Similar
$author =
    $book->author()
         ->create(['name' => 'John Doe']);
```

You can update the embedded model using the `save` method (available since release 2.0.0):

```php
$author = $book->author;

$author->name = 'Jane Doe';
$author->save();
```

You can replace the embedded model with a new model like this:

```php
$newAuthor = new Author(['name' => 'Jane Doe']);

$book->author()->save($newAuthor);
```

Query Builder
-------------

### Basic Usage

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

### Available operations

To see the available operations, check the [Eloquent](#eloquent) section.

Transactions
------------

Transactions require MongoDB version ^4.0 as well as deployment of replica set or sharded clusters. You can find more information [in the MongoDB docs](https://docs.mongodb.com/manual/core/transactions/)

### Basic Usage

```php
DB::transaction(function () {
    User::create(['name' => 'john', 'age' => 19, 'title' => 'admin', 'email' => 'john@example.com']);
    DB::collection('users')->where('name', 'john')->update(['age' => 20]);
    DB::collection('users')->where('name', 'john')->delete();
});
```

```php
// begin a transaction
DB::beginTransaction();
User::create(['name' => 'john', 'age' => 19, 'title' => 'admin', 'email' => 'john@example.com']);
DB::collection('users')->where('name', 'john')->update(['age' => 20]);
DB::collection('users')->where('name', 'john')->delete();

// commit changes
DB::commit();
```

To abort a transaction, call the `rollBack` method at any point during the transaction:

```php
DB::beginTransaction();
User::create(['name' => 'john', 'age' => 19, 'title' => 'admin', 'email' => 'john@example.com']);

// Abort the transaction, discarding any data created as part of it
DB::rollBack();
```

**NOTE:** Transactions in MongoDB cannot be nested. DB::beginTransaction() function will start new transactions in a new created or existing session and will raise the RuntimeException when transactions already exist. See more in MongoDB official docs [Transactions and Sessions](https://www.mongodb.com/docs/manual/core/transactions/#transactions-and-sessions)

```php
DB::beginTransaction();
User::create(['name' => 'john', 'age' => 20, 'title' => 'admin']);

// This call to start a nested transaction will raise a RuntimeException
DB::beginTransaction();
DB::collection('users')->where('name', 'john')->update(['age' => 20]);
DB::commit();
DB::rollBack();
```

Schema
------

The database driver also has (limited) schema builder support. You can easily manipulate collections and set indexes.

### Basic Usage

```php
Schema::create('users', function ($collection) {
    $collection->index('name');
    $collection->unique('email');
});
```

You can also pass all the parameters specified [in the MongoDB docs](https://docs.mongodb.com/manual/reference/method/db.collection.createIndex/#options-for-all-index-types) to the `$options` parameter:

```php
Schema::create('users', function ($collection) {
    $collection->index(
        'username',
        null,
        null,
        [
            'sparse' => true,
            'unique' => true,
            'background' => true,
        ]
    );
});
```

Inherited operations:

-   create and drop
-   collection
-   hasCollection
-   index and dropIndex (compound indexes supported as well)
-   unique

MongoDB specific operations:

-   background
-   sparse
-   expire
-   geospatial

All other (unsupported) operations are implemented as dummy pass-through methods because MongoDB does not use a predefined schema.

Read more about the schema builder on [Laravel Docs](https://laravel.com/docs/6.0/migrations#tables)

### Geospatial indexes

Geospatial indexes are handy for querying location-based documents.

They come in two forms: `2d` and `2dsphere`. Use the schema builder to add these to a collection.

```php
Schema::create('bars', function ($collection) {
    $collection->geospatial('location', '2d');
});
```

To add a `2dsphere` index:

```php
Schema::create('bars', function ($collection) {
    $collection->geospatial('location', '2dsphere');
});
```

Extending
---------

### Cross-Database Relationships

If you're using a hybrid MongoDB and SQL setup, you can define relationships across them.

The model will automatically return a MongoDB-related or SQL-related relation based on the type of the related model.

If you want this functionality to work both ways, your SQL-models will need to use the `Jenssegers\Mongodb\Eloquent\HybridRelations` trait.

**This functionality only works for `hasOne`, `hasMany` and `belongsTo`.**

The MySQL model should use the `HybridRelations` trait:

```php
use Jenssegers\Mongodb\Eloquent\HybridRelations;

class User extends Model
{
    use HybridRelations;

    protected $connection = 'mysql';

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}
```

Within your MongoDB model, you should define the relationship:

```php
use Jenssegers\Mongodb\Eloquent\Model;

class Message extends Model
{
    protected $connection = 'mongodb';

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

### Authentication

If you want to use Laravel's native Auth functionality, register this included service provider:

```php
Jenssegers\Mongodb\Auth\PasswordResetServiceProvider::class,
```

This service provider will slightly modify the internal DatabaseReminderRepository to add support for MongoDB based password reminders.

If you don't use password reminders, you don't have to register this service provider and everything else should work just fine.

### Queues

If you want to use MongoDB as your database backend, change the driver in `config/queue.php`:

```php
'connections' => [
    'database' => [
        'driver' => 'mongodb',
        // You can also specify your jobs specific database created on config/database.php
        'connection' => 'mongodb-job',
        'table' => 'jobs',
        'queue' => 'default',
        'expire' => 60,
    ],
],
```

If you want to use MongoDB to handle failed jobs, change the database in `config/queue.php`:

```php
'failed' => [
    'driver' => 'mongodb',
    // You can also specify your jobs specific database created on config/database.php
    'database' => 'mongodb-job',
    'table' => 'failed_jobs',
],
```

#### Laravel specific

Add the service provider in `config/app.php`:

```php
Jenssegers\Mongodb\MongodbQueueServiceProvider::class,
```

#### Lumen specific

With [Lumen](http://lumen.laravel.com), add the service provider in `bootstrap/app.php`. You must however ensure that you add the following **after** the `MongodbServiceProvider` registration.

```php
$app->make('queue');

$app->register(Jenssegers\Mongodb\MongodbQueueServiceProvider::class);
```

Upgrading
---------

#### Upgrading from version 2 to 3

In this new major release which supports the new MongoDB PHP extension, we also moved the location of the Model class and replaced the MySQL model class with a trait.

Please change all `Jenssegers\Mongodb\Model` references to `Jenssegers\Mongodb\Eloquent\Model` either at the top of your model files or your registered alias.

```php
use Jenssegers\Mongodb\Eloquent\Model;

class User extends Model
{
    //
}
```

If you are using hybrid relations, your MySQL classes should now extend the original Eloquent model class `Illuminate\Database\Eloquent\Model` instead of the removed `Jenssegers\Eloquent\Model`.

Instead use the new `Jenssegers\Mongodb\Eloquent\HybridRelations` trait. This should make things more clear as there is only one single model class in this package.

```php
use Jenssegers\Mongodb\Eloquent\HybridRelations;

class User extends Model
{

    use HybridRelations;

    protected $connection = 'mysql';
}
```

Embedded relations now return an `Illuminate\Database\Eloquent\Collection` rather than a custom Collection class. If you were using one of the special methods that were available, convert them to Collection operations.

```php
$books = $user->books()->sortBy('title')->get();
```

## Security contact information

To report a security vulnerability, follow [these steps](https://tidelift.com/security).
