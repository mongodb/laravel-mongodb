<?php

namespace MongoDB\Laravel;

use Illuminate\Bus\BatchFactory;
use Illuminate\Bus\BatchRepository;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Container\Container;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use MongoDB\Laravel\Bus\MongoBatchRepository;
use Override;

use function sprintf;

class MongoDBBusServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     */
    #[Override]
    public function register()
    {
        $this->app->singleton(MongoBatchRepository::class, function (Container $app) {
            $connection = $app->make('db')->connection($app->config->get('queue.batching.database'));

            if (! $connection instanceof Connection) {
                throw new InvalidArgumentException(sprintf('The "mongodb" batch driver requires a MongoDB connection. The "%s" connection uses the "%s" driver.', $connection->getName(), $connection->getDriverName()));
            }

            return new MongoBatchRepository(
                $app->make(BatchFactory::class),
                $connection,
                $app->config->get('queue.batching.collection', 'job_batches'),
            );
        });

        /** The {@see BatchRepository} service is registered in {@see BusServiceProvider} */
        $this->app->register(BusServiceProvider::class);
        $this->app->extend(BatchRepository::class, function (BatchRepository $repository, Container $app) {
            $driver = $app->config->get('queue.batching.driver');

            return match ($driver) {
                'mongodb' => $app->make(MongoBatchRepository::class),
                default => $repository,
            };
        });
    }

    /** @inheritdoc */
    #[Override]
    public function provides()
    {
        return [
            BatchRepository::class,
            MongoBatchRepository::class,
        ];
    }
}
