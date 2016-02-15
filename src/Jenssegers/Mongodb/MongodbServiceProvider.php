<?php namespace Jenssegers\Mongodb;

use Illuminate\Support\ServiceProvider;
use Jenssegers\Mongodb\Queue\MongoConnector;

class MongodbServiceProvider extends ServiceProvider
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

        // Support queue failer to record failed jobs
        $this->app->singleton('queue.failer', function ($app) {
            $config = $app['config']['queue.failed'];

            if (isset($config['collection'])) {
                return new DatabaseFailedJobProvider($app['db'], $config['database'], $config['collection']);
            } else {
                return new NullFailedJobProvider;
            }
        });
    }
}
