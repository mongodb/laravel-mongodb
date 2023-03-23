# Queues

If you want to use MongoDB as your database backend, change the driver in `config/queue.php`:

```php
'connections' => [
    'database' => [
        'driver' => 'mongodb',
        // You can also specify your jobs specific database created on config/database.php
        'connection' => 'mongodb-job',
        'table' => 'jobs',
        'queue' => 'default',
        'expire' => 60,
    ],
],
```

If you want to use MongoDB to handle failed jobs, change the database in `config/queue.php`:

```php
'failed' => [
    'driver' => 'mongodb',
    // You can also specify your jobs specific database created on config/database.php
    'database' => 'mongodb-job',
    'table' => 'failed_jobs',
],
```

## Laravel specific

Add the service provider in `config/app.php`:

```php
Jenssegers\Mongodb\MongodbQueueServiceProvider::class,
```

## Lumen specific

With [Lumen](http://lumen.laravel.com), add the service provider in `bootstrap/app.php`. You must however ensure that you add the following **after** the `MongodbServiceProvider` registration.

```php
$app->make('queue');

$app->register(Jenssegers\Mongodb\MongodbQueueServiceProvider::class);
```