Getting Started
===============

Installation
------------

Make sure you have the MongoDB PHP driver installed. You can find installation instructions at https://php.net/manual/en/mongodb.installation.php

Install the package via Composer:

```bash
$ composer require mongodb/laravel-mongodb
```

In case your Laravel version does NOT autoload the packages, add the service provider to `config/app.php`:

```php
'providers' => [
    // ...
    MongoDB\Laravel\MongoDBServiceProvider::class,
],
```

Configuration
-------------

To configure a new MongoDB connection, add a new connection entry to `config/database.php`:

```php
'default' => env('DB_CONNECTION', 'mongodb'),

'connections' => [
    'mongodb' => [
        'driver' => 'mongodb',
        'dsn' => env('DB_DSN'),
        'database' => env('DB_DATABASE', 'homestead'),
    ],
    // ...
],
```

The `dsn` key contains the connection string used to connect to your MongoDB deployment. The format and available options are documented in the [MongoDB documentation](https://docs.mongodb.com/manual/reference/connection-string/).

Instead of using a connection string, you can also use the `host` and `port` configuration options to have the connection string created for you.

```php
'connections' => [
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
],
```

The `options` key in the connection configuration corresponds to the [`uriOptions` parameter](https://www.php.net/manual/en/mongodb-driver-manager.construct.php#mongodb-driver-manager.construct-urioptions).

You are ready to [create your first MongoDB model](eloquent-models.md).
