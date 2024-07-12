<?php

declare(strict_types=1);

namespace MongoDB\Laravel;

use Illuminate\Queue\Failed\DatabaseFailedJobProvider;
use Illuminate\Queue\Failed\NullFailedJobProvider;
use Illuminate\Queue\QueueServiceProvider;

use function array_key_exists;

class MongoDBQueueServiceProvider extends QueueServiceProvider
{
    /**
     * Register the failed job services.
     *
     * @return void
     */
    protected function registerFailedJobServices()
    {
        $this->app->singleton('queue.failer', function ($app) {
            $config = $app['config']['queue.failed'];

            if (array_key_exists('driver', $config) && ($config['driver'] === null || $config['driver'] === 'null')) {
                return new NullFailedJobProvider();
            }

            if (isset($config['driver']) && $config['driver'] === 'mongodb') {
                return $this->mongoFailedJobProvider($config);
            }

            if (isset($config['driver']) && $config['driver'] === 'dynamodb') {
                return $this->dynamoFailedJobProvider($config);
            }

            if (isset($config['driver']) && $config['driver'] === 'database-uuids') {
                return $this->databaseUuidFailedJobProvider($config);
            }

            if (isset($config['table'])) {
                return $this->databaseFailedJobProvider($config);
            }

            return new NullFailedJobProvider();
        });
    }

    /**
     * Create a new MongoDB failed job provider.
     */
    protected function mongoFailedJobProvider(array $config): DatabaseFailedJobProvider
    {
        if (! isset($config['collection']) && isset($config['table'])) {
            $config['collection'] = $config['table'];
        }

        return new DatabaseFailedJobProvider(
            $this->app['db'],
            $config['database'] ?? null,
            $config['collection'] ?? 'failed_jobs',
        );
    }
}
