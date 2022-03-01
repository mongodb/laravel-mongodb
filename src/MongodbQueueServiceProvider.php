<?php

namespace Jenssegers\Mongodb;

use Illuminate\Queue\Failed\NullFailedJobProvider;
use Illuminate\Queue\QueueServiceProvider;
use Jenssegers\Mongodb\Queue\Failed\MongoFailedJobProvider;

class MongodbQueueServiceProvider extends QueueServiceProvider
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

            if (array_key_exists('driver', $config) &&
                (is_null($config['driver']) || $config['driver'] === 'null')) {
                return new NullFailedJobProvider;
            }

            if (isset($config['driver']) && $config['driver'] === 'mongodb') {
                return $this->mongoFailedJobProvider($config);
            } elseif (isset($config['driver']) && $config['driver'] === 'dynamodb') {
                return $this->dynamoFailedJobProvider($config);
            } elseif (isset($config['driver']) && $config['driver'] === 'database-uuids') {
                return $this->databaseUuidFailedJobProvider($config);
            } elseif (isset($config['table'])) {
                return $this->databaseFailedJobProvider($config);
            } else {
                return new NullFailedJobProvider;
            }
        });
    }

    /**
     * Create a new MongoDB failed job provider.
     */
    protected function mongoFailedJobProvider(array $config): MongoFailedJobProvider
    {
        return new MongoFailedJobProvider($this->app['db'], $config['database'], $config['table']);
    }
}
