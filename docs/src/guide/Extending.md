# Extending

## Cross-Database Relationships

If you're using a hybrid MongoDB and SQL setup, you can define relationships across them.

The model will automatically return a MongoDB-related or SQL-related relation based on the type of the related model.

If you want this functionality to work both ways, your SQL-models will need to use the `Jenssegers\Mongodb\Eloquent\HybridRelations` trait.

**This functionality only works for `hasOne`, `hasMany` and `belongsTo`.**

The MySQL model should use the `HybridRelations` trait:

```php
use Jenssegers\Mongodb\Eloquent\HybridRelations;

class User extends Model
{
    use HybridRelations;

    protected $connection = 'mysql';

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}
```
Within your MongoDB model, you should define the relationship:

```php
use Jenssegers\Mongodb\Eloquent\Model;

class Message extends Model
{
    protected $connection = 'mongodb';

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

## Authentication
If you want to use Laravel's native Auth functionality, register this included service provider:

```php
Jenssegers\Mongodb\Auth\PasswordResetServiceProvider::class,
```

This service provider will slightly modify the internal DatabaseReminderRepository to add support for MongoDB based password reminders.

If you don't use password reminders, you don't have to register this service provider and everything else should work just fine.

## Queues
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

### Laravel specific

Add the service provider in `config/app.php`:

```php
Jenssegers\Mongodb\MongodbQueueServiceProvider::class,
```

### Lumen specific

With [Lumen](http://lumen.laravel.com), add the service provider in `bootstrap/app.php`. You must however ensure that you add the following **after** the `MongodbServiceProvider` registration.

```php
$app->make('queue');

$app->register(Jenssegers\Mongodb\MongodbQueueServiceProvider::class);
```
