<?php

declare(strict_types=1);

namespace MongoDB\Laravel;

use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use MongoDB\Laravel\Cache\MongoStore;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Queue\MongoConnector;

use function assert;

class MongoDBServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     */
    public function boot()
    {
        Model::setConnectionResolver($this->app['db']);

        Model::setEventDispatcher($this->app['events']);
    }

    /**
     * Register the service provider.
     */
    public function register()
    {
        // Add database driver.
        $this->app->resolving('db', function ($db) {
            $db->extend('mongodb', function ($config, $name) {
                $config['name'] = $name;

                return new Connection($config);
            });
        });

        // Add cache and lock drivers.
        $this->app->resolving('cache', function (CacheManager $cache) {
            $cache->extend('mongodb', function (Application $app, array $config): Repository {
                // The closure is bound to the CacheManager
                assert($this instanceof CacheManager);

                $store = new MongoStore(
                    $app['db']->connection($config['connection'] ?? null),
                    $config['collection'] ?? 'cache',
                    $this->getPrefix($config),
                    $app['db']->connection($config['lock_connection'] ?? $config['connection'] ?? null),
                    $config['lock_collection'] ?? ($config['collection'] ?? 'cache') . '_locks',
                    $config['lock_lottery'] ?? [2, 100],
                    $config['lock_timeout'] ?? 86400,
                );

                return $this->repository($store, $config);
            });
        });

        // Add connector for queue support.
        $this->app->resolving('queue', function ($queue) {
            $queue->addConnector('mongodb', function () {
                return new MongoConnector($this->app['db']);
            });
        });
    }
}
