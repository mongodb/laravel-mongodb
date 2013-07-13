Laravel Eloquent MongoDB [![Build Status](https://travis-ci.org/jenssegers/Laravel-MongoDB.png?branch=master)](https://travis-ci.org/jenssegers/Laravel-MongoDB)
========================

An Eloquent model that supports MongoDB, inspired by LMongo but using original Eloquent methods.

*This model extends the original Eloquent model so it uses exactly the same methods. Please note that some advanced Eloquent features may not be working, but feel free to issue a pull request!*

Installation
------------

Add the package to your `composer.json` or install manually.

```yaml
{
    "require": {
        "jenssegers/mongodb": "*"
    }
}
```

Run `composer update` to download and install the package.

Add the service provider in `app/config/app.php`:

```php
'Jenssegers\Mongodb\MongodbServiceProvider',
```

Usage
-----

Tell your model to use the MongoDB model and a MongoDB collection (alias for table):

```php
use Jenssegers\Mongodb\Model as Eloquent

class MyModel extends Eloquent {

    protected $collection = 'mycollection';

}
```

Configuration
-------------

The model will automatically check the database configuration array in `app/config/database.php` for a 'mongodb' item.

```php
'mongodb' => array(
    'host'     => 'localhost',
    'port'     => 27017,
    'database' => 'database',
),
```

You can also specify the connection name in the model:

```php
class MyModel extends Eloquent {

    protected $connection = 'mongodb2';

}
```

Examples
--------

**Retrieving All Models**

```php
$users = User::all();
```

**Retrieving A Record By Primary Key**

```php
$user = User::find('517c43667db388101e00000f');
```

**Wheres**

```php
$users = User::where('votes', '>', 100)->take(10)->get();
```

**Or Statements**

```php
$users = User::where('votes', '>', 100)->orWhere('name', 'John')->get();
```

**Using Where In With An Array**

```php
$users = User::whereIn('age', array(16, 18, 20))->get();
```

When using `whereNotIn` objects will be returned if the field is non existant. Combine with `whereNotNull('age')` to leave out those documents.

**Using Where Between**

```php
$users = User::whereBetween('votes', array(1, 100))->get();
```

**Where null**

```php
$users = User::whereNull('updated_at')->get();
```

**Order By**

```php
$users = User::orderBy('name', 'desc')->get();
```

**Offset & Limit**

```php
$users = User::skip(10)->take(5)->get();
```

**Distinct**

Distinct requires a field for which to return the distinct values.

```php
$users = User::distinct()->get(array('name'));
// or
$users = User::distinct('name')->get();
```

Distinct can be combined with **where**:

```php
$users = User::where('active', true)->distinct('name')->get();
```

**Advanced Wheres**

```php
$users = User::where('name', '=', 'John')->orWhere(function($query)
        {
            $query->where('votes', '>', 100)
                  ->where('title', '<>', 'Admin');
        })
        ->get();
```

**Group By**

Selected columns that are not grouped will be aggregated with the $last function.

```php
$users = Users::groupBy('title')->get(array('title', 'name'));
```

**Aggregation**

```php
$total = Order::count();
$price = Order::max('price');
$price = Order::min('price');
$price = Order::avg('price');
$total = Order::sum('price');
```

Aggregations can be combined with **where**:

```php
$sold = Orders::where('sold', true)->sum('price');
```

**Like**

```php
$user = Comment::where('body', 'like', '%spam%')->get();
```

**Inserts, updates and deletes**

All basic insert, update, delete and select methods should be implemented.

**Increments & decrements**

Perform increments (default 1) on specified attributes.
Attention: without a where-clause, every object will be modified.
The number of updated objects is returned.

```php
User::where('name', 'John Doe')->increment('age');
User::where('name', 'Bart De Wever')->decrement('weight', 50);

$count = User->increment('age');
echo $count;
```

will return the number of users where `age` is a valid field.

These functions also allow for a third attribute:

```php
User::where('age', '29')->increment('age', 1, array('group' => 'thirty something'));

User::where('bmi', 30)->decrement('bmi', 1, array('category' => 'overweight'));
```