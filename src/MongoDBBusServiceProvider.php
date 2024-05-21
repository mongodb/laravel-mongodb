<?php

namespace MongoDB\Laravel;

use Illuminate\Bus\BatchFactory;
use Illuminate\Bus\BatchRepository;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Container\Container;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use MongoDB\Laravel\Bus\MongoBatchRepository;

class MongoDBBusServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     */
    public function register()
    {
        $this->app->singleton(MongoBatchRepository::class, function (Container $app) {
            return new MongoBatchRepository(
                $app->make(BatchFactory::class),
                $app->make('db')->connection($app->config->get('queue.batching.database')),
                $app->config->get('queue.batching.collection', 'job_batches'),
            );
        });

        /** @see BusServiceProvider::registerBatchServices() */
        $this->app->extend(BatchRepository::class, function (BatchRepository $repository, Container $app) {
            $driver = $app->config->get('queue.batching.driver');

            return match ($driver) {
                'mongodb' => $app->make(MongoBatchRepository::class),
                default => $repository,
            };
        });
    }

    public function provides()
    {
        return [
            BatchRepository::class,
            MongoBatchRepository::class,
        ];
    }
}
