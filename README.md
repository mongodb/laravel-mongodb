Laravel MongoDB
===============

[![Latest Stable Version](http://img.shields.io/github/release/jenssegers/laravel-mongodb.svg)](https://packagist.org/packages/jenssegers/mongodb) [![Total Downloads](http://img.shields.io/packagist/dm/jenssegers/mongodb.svg)](https://packagist.org/packages/jenssegers/mongodb) [![Build Status](http://img.shields.io/travis/jenssegers/laravel-mongodb.svg)](https://travis-ci.org/jenssegers/laravel-mongodb) [![Coverage Status](http://img.shields.io/coveralls/jenssegers/laravel-mongodb.svg)](https://coveralls.io/r/jenssegers/laravel-mongodb?branch=master)

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
        'host'     => array('server1', 'server2'),
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

Auth
----

If you want to use Laravel's native Auth functionality, register this included service provider:

    'Jenssegers\Mongodb\Auth\ReminderServiceProvider',

This service provider will slightly modify the internal DatabaseReminderRepository to add support for MongoDB based password reminders. If you don't use password reminders, you don't have to register this service provider and everything else should work just fine.

Sentry
------

If yo want to use this library with [Sentry](https://cartalyst.com/manual/sentry), then check out https://github.com/jenssegers/Laravel-MongoDB-Sentry

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

**And Statements**

    $users = User::where('votes', '>', 100)->where('name', '=', 'John')->get();
    
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

**Soft deleting**

When soft deleting a model, it is not actually removed from your database. Instead, a deleted_at timestamp is set on the record. To enable soft deletes for a model, apply the SoftDeletingTrait to the model:

use Jenssegers\Mongodb\Eloquent\SoftDeletingTrait;

class User extends Eloquent {

    use SoftDeletingTrait;

    protected $dates = ['deleted_at'];

}

For more information check http://laravel.com/docs/eloquent#soft-deleting

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

Other relations are not yet supported, but may be added in the future. Read more about these relations on http://laravel.com/docs/eloquent#relationships

### EmbedsMany Relations

If you want to embed documents, rather than referencing them, you can use the `embedsMany` relation:

    use Jenssegers\Mongodb\Model as Eloquent;

    class User extends Eloquent {

        public function books()
        {
            return $this->embedsMany('Book');
        }

    }

Now we can access the user's books through the dynamic property:

    $books = User::first()->books;

When using embedded documents, there will also be an inverse relation available:

    $user = $book->user;

Inserting and updating embedded documents works just like the `belongsTo` relation:

    $book = new Book(array('title' => 'A Game of Thrones'));

    $user = User::first();

    $book = $user->books()->save($book);

You can remove an embedded document by using the `destroy()` method:

    $book = $user->books()->first();

    $user->books()->destroy($book->_id);
    // or
    $user->books()->destroy($book);

If you want to add or remove embedded documents, without persistence, you can use the `associate` and `dissociate` methods. To write the changes to the database, save the parent object:

    $user->books()->associate($book);
    $user->save();

Again, you may override the conventional local key by passing a second argument to the embedsMany method:

    return $this->embedsMany('Book', 'local_key');

When using embedded documents, they will be stored in a _relation attribute of the parent document. This attribute is hidden by default when using `toArray` or `toJson`. If you want the attribute to be exposed, add it to `$exposed` property definition to your model:

    use Jenssegers\Mongodb\Model as Eloquent;

    class User extends Eloquent {

        protected $exposed = array('_books');

    }

### EmbedsOne Relations

There is also an EmbedsOne relation, which works similar to the EmbedsMany relation, but only stores one embedded model.

    use Jenssegers\Mongodb\Model as Eloquent;

    class Book extends Eloquent {

        public function author()
        {
            return $this->embedsOne('Author');
        }

    }

Now we can access the book's author through the dynamic property:

    $author = Book::first()->author;

Inserting and updating embedded documents works just like the `embedsMany` relation:

    $author = new Author(array('name' => 'John Doe'));

    $book = Books::first();

    $author = $user->author()->save($author);

### MySQL Relations

If you're using a hybrid MongoDB and SQL setup, you're in luck! The model will automatically return a MongoDB- or SQL-relation based on the type of the related model. Of course, if you want this functionality to work both ways, your SQL-models will need to extend `Jenssegers\Eloquent\Model`. Note that this functionality only works for hasOne, hasMany and belongsTo relations.

Example SQL-based User model:

    use Jenssegers\Eloquent\Model as Eloquent;

    class User extends Eloquent {

        protected $connection = 'mysql';

        public function messages()
        {
            return $this->hasMany('Message');
        }

    }

And the Mongodb-based Message model:

    use Jenssegers\Mongodb\Model as Eloquent;

    class Message extends Eloquent {

        protected $connection = 'mongodb';

        public function user()
        {
            return $this->belongsTo('User');
        }

    }

### Raw Expressions

These expressions will be injected directly into the query.

    User::whereRaw(array('age' => array('$gt' => 30, '$lt' => 40)))->get();

You can also perform raw expressions on the internal MongoCollection object. If this is executed on the model class, it will return a collection of models. If this is executed on the query builder, it will return the original response.

    // Returns a collection of User models.
    $models = User::raw(function($collection)
    {
        return $collection->find();
    });

    // Returns the original MongoCursor.
    $cursor = DB::collection('users')->raw(function($collection)
    {
        return $collection->find();
    });

Optional: if you don't pass a closure to the raw method, the internal MongoCollection object will be accessible:

    $model = User::raw()->findOne(array('age' => array('$lt' => 18)));

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

If you don't want duplicate items, set the third parameter to `true`:

    DB::collection('users')->where('name', 'John')->push('items', 'boots', true);

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
