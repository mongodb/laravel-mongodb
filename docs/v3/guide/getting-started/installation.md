## Installation

Make sure you have the MongoDB PHP driver installed. You can find installation instructions at http://php.net/manual/en/mongodb.installation.php

## Laravel version Compatibility

| Laravel | Package        | Maintained         |
| :------ | :------------- | :----------------- |
| 9.x     | 3.9.x          | :white_check_mark: |
| 8.x     | 3.8.x          | :white_check_mark: |
| 7.x     | 3.7.x          | :x:                |
| 6.x     | 3.6.x          | :x:                |
| 5.8.x   | 3.5.x          | :x:                |
| 5.7.x   | 3.4.x          | :x:                |
| 5.6.x   | 3.4.x          | :x:                |
| 5.5.x   | 3.3.x          | :x:                |
| 5.4.x   | 3.2.x          | :x:                |
| 5.3.x   | 3.1.x or 3.2.x | :x:                |
| 5.2.x   | 2.3.x or 3.0.x | :x:                |
| 5.1.x   | 2.2.x or 3.0.x | :x:                |
| 5.0.x   | 2.1.x          | :x:                |
| 4.2.x   | 2.0.x          | :x:                |

Install the package via Composer:

```bash
$ composer require jenssegers/mongodb
```

## Install the package via Composer

```bash
$ composer require jenssegers/mongodb
```

## Laravel

In case your Laravel version does NOT autoload the packages, add the service provider to `config/app.php`:

```php
Jenssegers\Mongodb\MongodbServiceProvider::class,
```

## Lumen

For usage with [Lumen](http://lumen.laravel.com), add the service provider in `bootstrap/app.php`. In this file, you will also need to enable Eloquent. You must however ensure that your call to `$app->withEloquent();` is **below** where you have registered the `MongodbServiceProvider`:

```php
$app->register(Jenssegers\Mongodb\MongodbServiceProvider::class);

$app->withEloquent();
```

The service provider will register a MongoDB database extension with the original database manager. There is no need to register additional facades or objects.

When using MongoDB connections, Laravel will automatically provide you with the corresponding MongoDB objects.

## Non-Laravel projects

For usage outside Laravel, check out the [Capsule manager](https://github.com/illuminate/database/blob/master/README.md) and add:

```php
$capsule->getDatabaseManager()->extend('mongodb', function($config, $name) {
    $config['name'] = $name;

    return new Jenssegers\Mongodb\Connection($config);
});
```