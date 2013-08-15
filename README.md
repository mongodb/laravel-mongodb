Laravel MongoDB [![Build Status](https://travis-ci.org/jenssegers/Laravel-MongoDB.png?branch=master)](https://travis-ci.org/jenssegers/Laravel-MongoDB)
===============

An Eloquent model and Query builder with support for MongoDB, inspired by LMongo, but using the original Laravel methods. *This library extends the original Laravel classes, so it uses exactly the same methods.*

**ATTENTION WHEN UPGRADING!**
The way the internal connection resolving works has been changed to extend original Laravel objects instead of registering new objects. Check the configuration section for more information about the new configuration. Remove the old `MDB` facade as it is now deprecated.

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

The service provider will register a mongodb extension with the original database manager, so that everything else keeps working just fine.

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

You can also specify the connection name in the model if you have multiple connections:

    use Jenssegers\Mongodb\Model as Eloquent;

    class MyModel extends Eloquent {
    
        protected $connection = 'mongodb2';
    
    }

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

Tell your model to use the MongoDB model and set the collection (alias for table) property:
    
    use Jenssegers\Mongodb\Model as Eloquent;
    
    class MyModel extends Eloquent {
    
        protected $collection = 'mycollection';
    
    }

Everything else works just like the original Eloquent model. Read more about the Eloquent on http://laravel.com/docs/eloquent

Query Builder
-------------

Once selected a mongodb connection, you can execute queries just like the original query builder. The main difference is that we are using `collection` instead of `table` (but table will work as well), and some additional operations like `push` and `pull`.
    
    // With custom connection
    $user = DB::connection('mongodb')->collection('users')->get();

    // Using default connection
    $users = DB::collection('users')->get();
    $user = DB::collection('users')->where('name', 'John')->first();

Read more about the query builder on http://laravel.com/docs/queries

Sessions
--------

If you want a MongoDB session driver, check out https://github.com/jenssegers/Laravel-MongoDB-Session

Documentation
-------------

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

When using `whereNotIn` objects will be returned if the field is non existant. Combine with `whereNotNull('age')` to leave out those documents.

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

### Inserts, updates and deletes

All basic insert, update, delete and select methods should be implemented.

### Relations

Supported relations are:

 - hasOne
 - hasMany
 - belongsTo

Example:

    class User extends Eloquent {

        public function items()
        {
            return $this->hasMany('Item');
        }

    }

And the inverse relation:

    class Item extends Eloquent {

        public function user()
        {
            return $this->belongsTo('User');
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

Or you can access the internal object directly:

    User::raw()->find();

### MongoDB specific operations

**Push**

Add one or more items to an array.

    User::where('name', 'John')->push('items', 'boots');
    User::where('name', 'John')->push('items', array('sword', 'shield'));

**Pull**

Remove one or more values from an array.

    User::where('name', 'John')->pull('items', 'boots');
    User::where('name', 'John')->pull('items', array('sword', 'shield'));

### Query Caching

You may easily cache the results of a query using the remember method:

    $users = User::remember(10)->get();
