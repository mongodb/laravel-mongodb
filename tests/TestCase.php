<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests;

use Illuminate\Auth\Passwords\PasswordResetServiceProvider as BasePasswordResetServiceProviderAlias;
use Illuminate\Foundation\Application;
use MongoDB\Laravel\Auth\PasswordResetServiceProvider;
use MongoDB\Laravel\MongoDBQueueServiceProvider;
use MongoDB\Laravel\MongoDBServiceProvider;
use MongoDB\Laravel\Tests\Models\User;
use MongoDB\Laravel\Validation\ValidationServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

use function array_search;

class TestCase extends OrchestraTestCase
{
    /**
     * Get application providers.
     *
     * @param  Application $app
     *
     * @return array
     */
    protected function getApplicationProviders($app)
    {
        $providers = parent::getApplicationProviders($app);

        unset($providers[array_search(BasePasswordResetServiceProviderAlias::class, $providers)]);

        return $providers;
    }

    /**
     * Get package providers.
     *
     * @param  Application $app
     *
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            MongoDBServiceProvider::class,
            MongoDBQueueServiceProvider::class,
            PasswordResetServiceProvider::class,
            ValidationServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  Application $app
     *
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // reset base path to point to our package's src directory
        //$app['path.base'] = __DIR__ . '/../src';

        $config = require 'config/database.php';

        $app['config']->set('app.key', 'ZsZewWyUJ5FsKp9lMwv4tYbNlegQilM7');

        $app['config']->set('database.default', 'mongodb');
        $app['config']->set('database.connections.mysql', $config['connections']['mysql']);
        $app['config']->set('database.connections.mongodb', $config['connections']['mongodb']);
        $app['config']->set('database.connections.mongodb2', $config['connections']['mongodb']);

        $app['config']->set('auth.model', User::class);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('cache.driver', 'array');

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
}
