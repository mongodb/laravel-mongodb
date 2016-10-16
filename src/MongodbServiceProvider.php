<?php

namespace Moloquent;

use Illuminate\Support\ServiceProvider;
use Moloquent\Eloquent\Model;
use Moloquent\Queue\MongoConnector;

class MongodbServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     */
    public function boot()
    {
        Model::setConnectionResolver($this->app['db']);

        Model::setEventDispatcher($this->app['events']);

        $this->loadSupportClasses();
    }

    /**
     * Register the service provider.
     */
    public function register()
    {
        // Add database driver.
        $this->app->resolving('db', function ($db) {
            $db->extend('mongodb', function ($config) {
                return new Connection($config);
            });
        });

        // Add connector for queue support.
        $this->app->resolving('queue', function ($queue) {
            $queue->addConnector('mongodb', function () {
                return new MongoConnector($this->app['db']);
            });
        });
    }

    private function loadSupportClasses()
    {
        if (!class_exists('Jenssegers\Mongodb\Eloquent\Model')) {
            require_once __DIR__.'/SupportClasses/Jenssegers/Model.php';
        }
    }
}
