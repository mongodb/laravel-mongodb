Laravel Eloquent MongoDB [![Build Status](https://travis-ci.org/jenssegers/Laravel-MongoDB.png?branch=master)](https://travis-ci.org/jenssegers/Laravel-MongoDB)
========================

An Eloquent model that supports MongoDB.

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

Tell your model to use the MongoDB model and a MongoDB collection:

```php
use Jenssegers\Mongodb\Model as Eloquent

class MyModel extends Eloquent {

    protected $collection = 'mycollection';

}
```

Configuration
-------------

The model will automatically check the Laravel database configuration array in `app/config/database.php` for a 'mongodb' item.

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

All basic insert, update, delete and select methods should be implemented. Feel free to fork and help completing this library!
