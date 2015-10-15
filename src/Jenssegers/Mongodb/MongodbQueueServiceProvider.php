<?php namespace Jenssegers\Mongodb;

use Illuminate\Support\ServiceProvider;
use Jenssegers\Mongodb\Queue\MongodbConnector;

class MongodbQueueServiceProvider extends ServiceProvider {

    /**
     * Bootstrap the application events.
     */
    public function boot()
    {

    }

    /**
     * Register the service provider.
     */
    public function register()
    {
        $this->app->resolving('queue', function ($queue)
        {
          $queue->extend('mongodb', function () {
            return new MongodbConnector($this->app['db']);
          });
        });
    }

}
