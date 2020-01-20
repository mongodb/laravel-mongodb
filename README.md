Laravel MongoDB
===============

[![Latest Stable Version](http://img.shields.io/github/release/jenssegers/laravel-mongodb.svg)](https://packagist.org/packages/jenssegers/mongodb) [![Total Downloads](http://img.shields.io/packagist/dm/jenssegers/mongodb.svg)](https://packagist.org/packages/jenssegers/mongodb) [![Build Status](https://img.shields.io/github/workflow/status/jenssegers/laravel-mongodb/CI)](https://github.com/jenssegers/laravel-mongodb/actions) [![Coverage Status](https://coveralls.io/repos/github/jenssegers/laravel-mongodb/badge.svg?branch=master)](https://coveralls.io/github/jenssegers/laravel-mongodb?branch=master) [![Donate](https://img.shields.io/badge/donate-paypal-blue.svg)](https://www.paypal.me/jenssegers)

Laravel Eloquent add support for ODM (Object Document Mapper) to Laravel. It's the same as Eloquent ORM, but with Documents, since MongoDB is a NoSQL database.

Table of contents
-----------------
* [Installation](#installation)
* [Upgrading](#upgrading)
* [Configuration](#configuration)
* [Eloquent](#eloquent)
* [Query Builder](#query-builder)

Laravel Installation
------------
Make sure you have the MongoDB PHP driver installed. You can find installation instructions at http://php.net/manual/en/mongodb.installation.php

### Laravel version Compatibility

 Laravel  | Package
:---------|:----------
 4.2.x    | 2.0.x
 5.0.x    | 2.1.x
 5.1.x    | 2.2.x or 3.0.x
 5.2.x    | 2.3.x or 3.0.x
 5.3.x    | 3.1.x or 3.2.x
 5.4.x    | 3.2.x
 5.5.x    | 3.3.x
 5.6.x    | 3.4.x
 5.7.x    | 3.4.x
 5.8.x    | 3.5.x
 6.0.x    | 3.6.x

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

Upgrading
---------

#### Upgrading from version 2 to 3

In this new major release which supports the new MongoDB PHP extension, we also moved the location of the Model class and replaced the MySQL model class with a trait.

Please change all `Jenssegers\Mongodb\Model` references to `Jenssegers\Mongodb\Eloquent\Model` either at the top of your model files, or your registered alias.

```php
use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class User extends Eloquent
{
    //
}
```

If you are using hybrid relations, your MySQL classes should now extend the original Eloquent model class `Illuminate\Database\Eloquent\Model` instead of the removed `Jenssegers\Eloquent\Model`.

Instead use the new `Jenssegers\Mongodb\Eloquent\HybridRelations` trait. This should make things more clear as there is only one single model class in this package.

```php
use Jenssegers\Mongodb\Eloquent\HybridRelations;

class User extends Eloquent
{

    use HybridRelations;

    protected $connection = 'mysql';
}
```

Embedded relations now return an `Illuminate\Database\Eloquent\Collection` rather than a custom Collection class. If you were using one of the special methods that were available, convert them to Collection operations.

```php
$books = $user->books()->sortBy('title')->get();
```

Testing
-------

To run the test for this package, run:

```
docker-compose up
```

Configuration
-------------
You can use MongoDB either as a main database, either as a side database. To do so, add a new `mongodb` connection to `config/database.php`:

```php
'mongodb' => [
    'driver'   => 'mongodb',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', 27017),
    'database' => env('DB_DATABASE', 'homestead'),
    'username' => env('DB_USERNAME', 'homestead'),
    'password' => env('DB_PASSWORD', 'secret'),
    'options' => [
        'database' => 'admin', // required with Mongo 3+

        // here you can pass more settings
        // see https://www.php.net/manual/en/mongoclient.construct.php under "Parameters" for a list of complete parameters you can use
    ],
],
```

For multiple servers or replica set configurations, set the host to array and specify each server host:

```php
'mongodb' => [
    'driver'   => 'mongodb',
    'host' => ['server1', 'server2', ...],
    ...
    'options' => [
        'replicaSet' => 'rs0',
    ],
],
```

If you wish to use a connection string instead of a full key-value params, you can set it so. Check the documentation on MongoDB's URI format: https://docs.mongodb.com/manual/reference/connection-string/

```php
'mongodb' => [
    'driver'   => 'mongodb',
    'dsn' => env('DB_DSN'),
    'database' => env('DB_DATABASE', 'homestead'),
],
```

Eloquent
--------

### Basic Usage
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

**NOTE:** MongoDb documents are automatically stored with an unique ID that is stored in the `_id` property. If you wish to use your own ID, substitude the `$primaryKey` property and set it to your own primary key attribute name.

```php
use Jenssegers\Mongodb\Eloquent\Model;

class Book extends Model
{
    protected $primaryKey = 'id';
}

// Mongo will also createa _id, but the 'id' property will be used for primary key actions like find().
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
