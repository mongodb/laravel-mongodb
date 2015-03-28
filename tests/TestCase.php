<?php

class TestCase extends Orchestra\Testbench\TestCase {

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return array(
            'Jenssegers\Mongodb\MongodbServiceProvider',
            'Jenssegers\Mongodb\Auth\PasswordResetServiceProvider',
        );
    }

    /**
     * Define environment setup.
     *
     * @param  Illuminate\Foundation\Application    $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // reset base path to point to our package's src directory
        //$app['path.base'] = __DIR__ . '/../src';

        $config = require 'config/database.php';

        $app['config']->set('database.default', 'mongodb');
        $app['config']->set('database.connections.mysql', $config['connections']['mysql']);
        $app['config']->set('database.connections.mongodb', $config['connections']['mongodb']);

        $app['config']->set('auth.model', 'User');
        $app['config']->set('cache.driver', 'array');
    }

}
