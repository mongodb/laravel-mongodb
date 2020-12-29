---
title: Configuration
---
# Configuration
You can use MongoDB either as the main database, either as a side database. To do so, add a new `mongodb` connection to `config/database.php`:

```php
'mongodb' => [
    'driver' => 'mongodb',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', 27017),
    'database' => env('DB_DATABASE', 'homestead'),
    'username' => env('DB_USERNAME', 'homestead'),
    'password' => env('DB_PASSWORD', 'secret'),
    'options' => [
        // here you can pass more settings to the Mongo Driver Manager
        // see https://www.php.net/manual/en/mongodb-driver-manager.construct.php under "Uri Options" for a list of complete parameters that you can use

        'database' => env('DB_AUTHENTICATION_DATABASE', 'admin'), // required with Mongo 3+
    ],
],
```

For multiple servers or replica set configurations, set the host to an array and specify each server host:

```php
'mongodb' => [
    'driver' => 'mongodb',
    'host' => ['server1', 'server2', ...],
    ...
    'options' => [
        'replicaSet' => 'rs0',
    ],
],
```

If you wish to use a connection string instead of full key-value params, you can set it so. Check the documentation on MongoDB's URI format: https://docs.mongodb.com/manual/reference/connection-string/

```php
'mongodb' => [
    'driver' => 'mongodb',
    'dsn' => env('DB_DSN'),
    'database' => env('DB_DATABASE', 'homestead'),
],
```