Laravel Eloquent MongoDB
========================

An Eloquent model that supports MongoDB.

Installation
------------

Add the package to your `composer.json` or install manually.

    {
        "require": {
            "jenssegers/mongodb": "*"
        }
    }


Run `composer update` to download and install the package.

Add the service provider in `app/config/app.php`:

    'Jenssegers\Mongodb\MongodbServiceProvider',

Usage
-----

Tell your model to use the MongoDB model and a MongoDB collection:

    use Jenssegers\Mongodb\Model as Eloquent

    class MyModel extends Eloquent {

        protected $collection = 'mycollection';

    }

Configuration
-------------

The model will automatically check the Laravel database configuration array in `app/config/database.php` for a 'mongodb' item.

    'mongodb' => array(
        'host'     => 'localhost',
        'port'     => 27017,
        'database' => 'database',
    ),

You can also specify the connection name in the model:

    class MyModel extends Eloquent {

        protected $connection = 'mongodb2';

    }

Examples
--------

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
    $total = User::sum('votes');

**Like**

    $user = Comment::where('body', 'like', '%spam%')->get();

All basic insert, update, delete and select methods should be implemented. Feel free to fork and help completing this library!