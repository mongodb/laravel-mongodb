# Configuration

The `dsn` key contains the connection string used to connect to your MongoDB deployment. The format and available options are documented in the [MongoDB documentation](https://docs.mongodb.com/manual/reference/connection-string/).

Instead of using a connection string, you can also use the `host` and `port` configuration options to have the connection string created for you.

```php
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
```

The `options` key in the connection configuration corresponds to the [`uriOptions` parameter](https://www.php.net/manual/en/mongodb-driver-manager.construct.php#mongodb-driver-manager.construct-urioptions).