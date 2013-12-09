Laravel MongoDB
===============

[![Latest Stable Version](https://poser.pugx.org/jenssegers/mongodb/v/stable.png)](https://packagist.org/packages/jenssegers/mongodb) [![Total Downloads](https://poser.pugx.org/jenssegers/mongodb/downloads.png)](https://packagist.org/packages/jenssegers/mongodb) [![Build Status](https://travis-ci.org/jenssegers/Laravel-MongoDB.png?branch=master)](https://travis-ci.org/jenssegers/Laravel-MongoDB)

An Eloquent model and Query builder with support for MongoDB, inspired by LMongo, but using the original Laravel methods. *This library extends the original Laravel classes, so it uses exactly the same methods.*

Installation
------------

Add the package to your `composer.json` and run `composer update`.

    {
        "require": {
            "jenssegers/mongodb": "*"
        }
    }

Add the service provider in `app/config/app.php`:

    'Jenssegers\Mongodb\MongodbServiceProvider',

The service provider will register a mongodb database extension with the original database manager. There is no need to register additional facades or objects. When using mongodb connections, Laravel will automatically provide you with the corresponding mongodb objects.

Configuration
-------------

Change your default database connection name in `app/config/database.php`:

    'default' => 'mongodb',

And add a new mongodb connection:

    'mongodb' => array(
        'driver'   => 'mongodb',
        'host'     => 'localhost',
        'port'     => 27017,
        'username' => 'username',
        'password' => 'password',
        'database' => 'database'
    ),

You can connect to multiple servers or replica sets with the following configuration:

    'mongodb' => array(
        'driver'   => 'mongodb',
        'host'     => array('server1', 'server2),
        'port'     => 27017,
        'username' => 'username',
        'password' => 'password',
        'database' => 'database',
        'options'  => array('replicaSet' => 'replicaSetName')
    ),

Eloquent
--------

Tell your model to use the MongoDB model and set the collection (alias for table) property. The lower-case, plural name of the class will be used for the collection name, unless another name is explicitly specified.

    use Jenssegers\Mongodb\Model as Eloquent;

    class MyModel extends Eloquent {

        protected $collection = 'mycollection';

    }

If you are using a different database driver as the default one, you will need to specify the mongodb connection within your model by changing the `connection` property:

    use Jenssegers\Mongodb\Model as Eloquent;

    class MyModel extends Eloquent {

        protected $connection = 'mongodb';

    }

Everything else works just like the original Eloquent model. Read more about the Eloquent on http://laravel.com/docs/eloquent

### Optional: Alias

You may also register an alias for the MongoDB model by adding the following to the alias array in `app/config/app.php`:

    'Moloquent'       => 'Jenssegers\Mongodb\Model',

This will allow you to use your registered alias like:

    class MyModel extends Moloquent {

        protected $collection = 'mycollection';

    }

Query Builder
-------------

The database driver plugs right into the original query builder. When using mongodb connections you will be able to build fluent queries to perform database operations. For your convenience, there is a `collection` alias for `table` as well as some additional mongodb specific operators/operations.

    // With custom connection
    $user = DB::connection('mongodb')->collection('users')->get();

    // Using default connection
    $users = DB::collection('users')->get();
    $user = DB::collection('users')->where('name', 'John')->first();

Read more about the query builder on http://laravel.com/docs/queries

Schema
------

The database driver also has (limited) schema builder support. You can easily manipulate collections and set indexes:

    Schema::create('users', function($collection)
    {
        $collection->index('name');
        $collection->unique('email');
    });

Supported operations are:

 - create and drop
 - collection
 - hasCollection
 - index and dropIndex (compound indexes supported as well)
 - unique
 - background, sparse, expire (MongoDB specific)

Read more about the schema builder on http://laravel.com/docs/schema

Sessions
--------

The MongoDB session driver is available in a separate package, check out https://github.com/jenssegers/Laravel-MongoDB-Session

Examples
--------

### Basic Usage

**Retrieving All Models**

    $users = User::all();

**Retrieving A Record By Primary Key**

    $user = User::find('517c43667db388101e00000f');

**Wheres**

    $users = User::where('votes', '>', 100)->take(10)->get();

**Or Statements**

    $users = User::where('votes', '>', 100)->orWhere('name', 'John')->get();

**Using Where In With An Array**

    $users = User::whereIn('age', array(16, 18, 20))->get();

When using `whereNotIn` objects will be returned if the field is non existent. Combine with `whereNotNull('age')` to leave out those documents.

**Using Where Between**

    $users = User::whereBetween('votes', array(1, 100))->get();

**Where null**

    $users = User::whereNull('updated_at')->get();

**Order By**

    $users = User::orderBy('name', 'desc')->get();

**Offset & Limit**

    $users = User::skip(10)->take(5)->get();

**Distinct**

Distinct requires a field for which to return the distinct values.

    $users = User::distinct()->get(array('name'));
    // or
    $users = User::distinct('name')->get();

Distinct can be combined with **where**:

    $users = User::where('active', true)->distinct('name')->get();

**Advanced Wheres**

    $users = User::where('name', '=', 'John')->orWhere(function($query)
        {
            $query->where('votes', '>', 100)
                  ->where('title', '<>', 'Admin');
        })
        ->get();

**Group By**

Selected columns that are not grouped will be aggregated with the $last function.

    $users = Users::groupBy('title')->get(array('title', 'name'));

**Aggregation**

*Aggregations are only available for MongoDB versions greater than 2.2.*

    $total = Order::count();
    $price = Order::max('price');
    $price = Order::min('price');
    $price = Order::avg('price');
    $total = Order::sum('price');

Aggregations can be combined with **where**:

    $sold = Orders::where('sold', true)->sum('price');

**Like**

    $user = Comment::where('body', 'like', '%spam%')->get();

**Incrementing or decrementing a value of a column**

Perform increments or decrements (default 1) on specified attributes:

    User::where('name', 'John Doe')->increment('age');
    User::where('name', 'Jaques')->decrement('weight', 50);

The number of updated objects is returned:

    $count = User->increment('age');

You may also specify additional columns to update:

    User::where('age', '29')->increment('age', 1, array('group' => 'thirty something'));
    User::where('bmi', 30)->decrement('bmi', 1, array('category' => 'overweight'));

### MongoDB specific operators

**Exists**

Matches documents that have the specified field.

    User::where('age', 'exists', true)->get();

**All**

Matches arrays that contain all elements specified in the query.

    User::where('roles', 'all', array('moderator', 'author'))->get();

**Size**

Selects documents if the array field is a specified size.

    User::where('tags', 'size', 3)->get();

**Regex**

Selects documents where values match a specified regular expression.

    User::where('name', 'regex', new MongoRegex("/.*doe/i"))->get();

**Type**

Selects documents if a field is of the specified type. For more information check: http://docs.mongodb.org/manual/reference/operator/query/type/#op._S_type

    User::where('age', 'type', 2)->get();

**Mod**

Performs a modulo operation on the value of a field and selects documents with a specified result.

    User::where('age', 'mod', array(10, 0))->get();

**Where**

Matches documents that satisfy a JavaScript expression. For more information check http://docs.mongodb.org/manual/reference/operator/query/where/#op._S_where

### Inserts, updates and deletes

All basic insert, update, delete and select methods should be implemented.

### Dates

Eloquent allows you to work with Carbon/DateTime objects instead of MongoDate objects. Internally, these dates will be converted to MongoDate objects when saved to the database. If you wish to use this functionality on non-default date fields you will need to manually specify them as described here: http://laravel.com/docs/eloquent#date-mutators

Example:

    use Jenssegers\Mongodb\Model as Eloquent;

    class User extends Eloquent {

        protected $dates = array('birthday');

    }

Which allows you to execute queries like:

    $users = User::where('birthday', '>', new DateTime('-18 years'))->get();

### Relations

Supported relations are:

 - hasOne
 - hasMany
 - belongsTo
 - belongsToMany

Example:

    use Jenssegers\Mongodb\Model as Eloquent;

    class User extends Eloquent {

        public function items()
        {
            return $this->hasMany('Item');
        }

    }

And the inverse relation:

    use Jenssegers\Mongodb\Model as Eloquent;

    class Item extends Eloquent {

        public function user()
        {
            return $this->belongsTo('User');
        }

    }

The belongsToMany relation will not use a pivot "table", but will push id's to a __related_ids__ attribute instead. This makes the second parameter for the belongsToMany method useless. If you want to define custom keys for your relation, set it to `null`:

    use Jenssegers\Mongodb\Model as Eloquent;

    class User extends Eloquent {

        public function groups()
        {
            return $this->belongsToMany('Group', null, 'users', 'groups');
        }

    }

Other relations are not yet supported, but may be added in the future. Read more about these relations on http://four.laravel.com/docs/eloquent#relationships

### Raw Expressions

These expressions will be injected directly into the query.

    User::whereRaw(array('age' => array('$gt' => 30, '$lt' => 40)))->get();

You can also perform raw expressions on the internal MongoCollection object, note that this will return the original response, and not a collection of models.

    User::raw(function($collection)
    {
        return $collection->find();
    });

Or you can access the internal MongoCollection object directly:

    User::raw()->find();

The MongoClient and MongoDB objects can be accessed like this:

    $client = DB::getMongoClient();
    $db = DB::getMongoDB();

### MongoDB specific operations

**Upsert**

Update or insert a document. Additional options for the update method are passed directly to the native update method.

    DB::collection('users')->where('name', 'John')
                           ->update($data, array('upsert' => true));

**Push**

Add an items to an array.

    DB::collection('users')->where('name', 'John')->push('items', 'boots');
    DB::collection('users')->where('name', 'John')->push('messages', array('from' => 'Jane Doe', 'message' => 'Hi John'));

**Pull**

Remove an item from an array.

    DB::collection('users')->where('name', 'John')->pull('items', 'boots');
    DB::collection('users')->where('name', 'John')->pull('messages', array('from' => 'Jane Doe', 'message' => 'Hi John'));

**Unset**

Remove one or more fields from a document.

    DB::collection('users')->where('name', 'John')->unset('note');

You can also perform an unset on a model.

    $user = User::where('name', 'John')->first();
    $user->unset('note');

### Query Caching

You may easily cache the results of a query using the remember method:

    $users = User::remember(10)->get();

*From: http://laravel.com/docs/queries#caching-queries*

### Query Logging

By default, Laravel keeps a log in memory of all queries that have been run for the current request. However, in some cases, such as when inserting a large number of rows, this can cause the application to use excess memory. To disable the log, you may use the `disableQueryLog` method:

    DB::connection()->disableQueryLog();

*From: http://laravel.com/docs/database#query-logging*
