Queues
======

If you want to use MongoDB as your database backend for Laravel Queue, change the driver in `config/queue.php`:

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

Add the service provider in `config/app.php`:

```php
MongoDB\Laravel\MongoDBQueueServiceProvider::class,
```
