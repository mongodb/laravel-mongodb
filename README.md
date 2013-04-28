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

    $users = User::whereIn('id', array(1, 2, 3))->get();

**Order By**

    $users = User::orderBy('name', 'desc')->get();

**Advanced Wheres**

    $users = User::where('name', '=', 'John')->orWhere(function($query)
            {
                $query->where('votes', '>', 100)
                      ->where('title', '<>', 'Admin');
            })
            ->get();

**Distinct**

Distinct is limited to a single column.

    $users = Users::distinct('name')->get();

**Group By**

Grouping does not support sorting and limiting at this moment.

    $users = Users::groupBy('title')->get();

All basis insert, update, delete and select methods should be implemented. Feel free to fork and help completing this library!