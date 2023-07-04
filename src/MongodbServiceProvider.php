<?php

namespace Jenssegers\Mongodb;

use Illuminate\Bus\BatchFactory;
use Illuminate\Bus\BatchRepository;
use Illuminate\Support\ServiceProvider;
use Jenssegers\Mongodb\Eloquent\Model;
use Jenssegers\Mongodb\Queue\MongoConnector;
use Jenssegers\Mongodb\Bus\MongodbBatchRepository;

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
            $db->extend('mongodb', function ($config, $name) {
                $config['name'] = $name;

                return new Connection($config);
            });
        });

        // Add connector for queue support.
        $this->app->resolving('queue', function ($queue) {
            $queue->addConnector('mongodb', function () {
                return new MongoConnector($this->app['db']);
            });
        });

        $this->app->extend(BatchRepository::class, function () {
            return new MongodbBatchRepository(
                app()->make(BatchFactory::class),
                app()->make('db')->connection(app()->config->get('queue.batching.database')),
                app()->config->get('queue.batching.table', 'job_batches')
            );
        });

        $this->app->bind(MongodbBatchRepository::class, function ($app) {
            return new MongodbBatchRepository(
                $app->make(BatchFactory::class),
                $app->make('db')->connection($app->config->get('queue.batching.database')),
                $app->config->get('queue.batching.table', 'job_batches')
            );
        });
    }
}
