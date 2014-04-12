<?php

class TestCase extends Orchestra\Testbench\TestCase {

    /**
     * Get package providers.
     *
     * @return array
     */
    protected function getPackageProviders()
    {
        return array('Jenssegers\Mongodb\MongodbServiceProvider');
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

        // load custom config
        $config = require 'config/database.php';

        // set mongodb as default connection
        $app['config']->set('database.default', 'mongodb');

        // overwrite database configuration
        $app['config']->set('database.connections.mysql', $config['connections']['mysql']);
        $app['config']->set('database.connections.mongodb', $config['connections']['mongodb']);

        // overwrite cache configuration
        $app['config']->set('cache.driver', 'array');
    }

}
