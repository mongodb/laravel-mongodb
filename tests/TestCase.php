<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests;

use Illuminate\Foundation\Application;
use MongoDB\Driver\Exception\ServerException;
use MongoDB\Laravel\MongoDBServiceProvider;
use MongoDB\Laravel\Schema\Builder;
use MongoDB\Laravel\Tests\Models\User;
use MongoDB\Laravel\Validation\ValidationServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

use function version_compare;

class TestCase extends OrchestraTestCase
{
    /**
     * Get package providers.
     *
     * @param  Application $app
     */
    protected function getPackageProviders($app): array
    {
        return [
            MongoDBServiceProvider::class,
            ValidationServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  Application $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        // reset base path to point to our package's src directory
        //$app['path.base'] = __DIR__ . '/../src';

        $config = require 'config/database.php';

        $app['config']->set('app.key', 'ZsZewWyUJ5FsKp9lMwv4tYbNlegQilM7');

        $app['config']->set('database.default', 'mongodb');
        $app['config']->set('database.connections.sqlite', $config['connections']['sqlite']);
        $app['config']->set('database.connections.mongodb', $config['connections']['mongodb']);
        $app['config']->set('database.connections.mongodb2', $config['connections']['mongodb']);

        $app['config']->set('auth.model', User::class);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('cache.driver', 'array');

        $app['config']->set('cache.stores.mongodb', [
            'driver' => 'mongodb',
            'connection' => 'mongodb',
            'collection' => 'foo_cache',
        ]);

        $app['config']->set('queue.default', 'database');
        $app['config']->set('queue.connections.database', [
            'driver' => 'mongodb',
            'table' => 'jobs',
            'queue' => 'default',
            'expire' => 60,
        ]);
        $app['config']->set('queue.failed.database', 'mongodb2');
        $app['config']->set('queue.failed.driver', 'mongodb');
    }

    public function skipIfSearchIndexManagementIsNotSupported(): void
    {
        try {
            $this->getConnection('mongodb')->getCollection('test')->listSearchIndexes(['name' => 'just_for_testing']);
        } catch (ServerException $e) {
            if (Builder::isAtlasSearchNotSupportedException($e)) {
                self::markTestSkipped('Search index management is not supported on this server');
            }

            throw $e;
        }
    }

    /**
     * Skip when Queryable Encryption is not usable on the connection: the
     * encryptedFieldsMap must be configured and the server must be recent
     * enough (8.0+ for range and Community support) and a replica set or a
     * sharded cluster.
     */
    public function skipIfQEIsNotSupported(): void
    {
        $connection = $this->getConnection('mongodb');

        if (! $connection->isAutoEncryptionEnabled('patients') && ! $connection->isAutoEncryptionEnabled('users')) {
            self::markTestSkipped('Queryable Encryption is not configured on the "mongodb" connection.');
        }

        if (version_compare($connection->getServerVersion(), '8.0', '<')) {
            self::markTestSkipped('Queryable Encryption requires MongoDB 8.0 or later.');
        }
    }
}
