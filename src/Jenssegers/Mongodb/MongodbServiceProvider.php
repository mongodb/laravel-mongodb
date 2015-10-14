<?php namespace Jenssegers\Mongodb;

use Illuminate\Support\ServiceProvider;
use Jenssegers\Mongodb\Queue\MongodbConnector;

class MongodbServiceProvider extends ServiceProvider {

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
        $this->app->resolving('db', function ($db)
        {
            $db->extend('mongodb', function ($config)
            {
                return new Connection($config);
            });
        });

        $this->app->resolving('queue', function($queue)
        {
          $queue->addConnector('mongodb', function() {
            return new MongodbConnector($this->app['db']);
          });
        });

    }

}
