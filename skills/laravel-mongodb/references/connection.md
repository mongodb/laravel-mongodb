# Connection

## `config/database.php`

```php
<?php

return [
    'default' => env('DB_CONNECTION', 'mongodb'),

    'connections' => [
        'mongodb' => [
            'driver'   => 'mongodb',
            'dsn'      => env('MONGODB_URI', 'mongodb://localhost:27017'),
            'database' => env('MONGODB_DATABASE', 'laravel'),
            'options'  => [
                'appName' => env('APP_NAME', 'laravel'),
            ],
        ],

        'mysql' => [
            'driver' => 'mysql',
            // ...
        ],
    ],
];
```

## `.env`

```
DB_CONNECTION=mongodb
MONGODB_URI="mongodb+srv://user:pass@cluster0.mongodb.net/"
MONGODB_DATABASE=laravel
```

Pool sizing, timeouts, and TLS all go into the URI (use `mongodb+srv://` for Atlas).

## Service-provider registration

Auto-registered via package discovery. If opted out, add to `bootstrap/providers.php`:

```php
return [
    MongoDB\Laravel\MongoDBServiceProvider::class,
];
```

## Using the connection from code

```php
use Illuminate\Support\Facades\DB;

DB::connection('mongodb')->table('logs')->insert(['msg' => 'hi']);

$db     = DB::connection('mongodb')->getDatabase();   // MongoDB\Database
$client = DB::connection('mongodb')->getClient();     // MongoDB\Client
// deprecated: getMongoDB() → getDatabase(), getMongoClient() → getClient()

$collection = DB::connection('mongodb')->getCollection('logs');
// note: ->collection() does not exist; use ->table() for the query builder
```

## Connection pooling and timeouts

```
mongodb+srv://.../db?maxPoolSize=50&serverSelectionTimeoutMS=5000&socketTimeoutMS=30000
```
