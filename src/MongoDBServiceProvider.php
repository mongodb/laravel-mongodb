<?php

declare(strict_types=1);

namespace MongoDB\Laravel;

use Closure;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use League\Flysystem\Filesystem;
use League\Flysystem\GridFS\GridFSAdapter;
use League\Flysystem\ReadOnly\ReadOnlyFilesystemAdapter;
use MongoDB\GridFS\Bucket;
use MongoDB\Laravel\Cache\MongoStore;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Queue\MongoConnector;
use RuntimeException;

use function assert;
use function class_exists;
use function get_debug_type;
use function is_string;
use function sprintf;

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

        $this->registerFlysystemAdapter();
    }

    private function registerFlysystemAdapter(): void
    {
        // GridFS adapter for filesystem
        $this->app->resolving('filesystem', static function (FilesystemManager $filesystemManager) {
            $filesystemManager->extend('gridfs', static function (Application $app, array $config) {
                if (! class_exists(GridFSAdapter::class)) {
                    throw new RuntimeException('GridFS adapter for Flysystem is missing. Try running "composer require league/flysystem-gridfs"');
                }

                $bucket = $config['bucket'] ?? null;

                if ($bucket instanceof Closure) {
                    // Get the bucket from a factory function
                    $bucket = $bucket($app, $config);
                } elseif (is_string($bucket) && $app->has($bucket)) {
                    // Get the bucket from a service
                    $bucket = $app->get($bucket);
                } elseif (is_string($bucket) || $bucket === null) {
                    // Get the bucket from the database connection
                    $connection = $app['db']->connection($config['connection']);
                    if (! $connection instanceof Connection) {
                        throw new InvalidArgumentException(sprintf('The database connection "%s" does not use the "mongodb" driver.', $config['connection'] ?? $app['config']['database.default']));
                    }

                    $bucket = $connection->getMongoClient()
                        ->selectDatabase($config['database'] ?? $connection->getDatabaseName())
                        ->selectGridFSBucket(['bucketName' => $config['bucket'] ?? 'fs', 'disableMD5' => true]);
                }

                if (! $bucket instanceof Bucket) {
                    throw new InvalidArgumentException(sprintf('Unexpected value for GridFS "bucket" configuration. Expecting "%s". Got "%s"', Bucket::class, get_debug_type($bucket)));
                }

                $adapter = new GridFSAdapter($bucket, $config['prefix'] ?? '');

                /** @see FilesystemManager::createFlysystem() */
                if ($config['read-only'] ?? false) {
                    if (! class_exists(ReadOnlyFilesystemAdapter::class)) {
                        throw new RuntimeException('Read-only Adapter for Flysystem is missing. Try running "composer require league/flysystem-read-only"');
                    }

                    $adapter = new ReadOnlyFilesystemAdapter($adapter);
                }

                /** Prevent using backslash on Windows in {@see FilesystemAdapter::__construct()} */
                $config['directory_separator'] = '/';

                return new FilesystemAdapter(new Filesystem($adapter, $config), $adapter, $config);
            });
        });
    }
}
