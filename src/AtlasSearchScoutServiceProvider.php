<?php

namespace MongoDB\Laravel;

use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Laravel\Scout\EngineManager;
use MongoDB\Laravel\Scout\AtlasSearchEngine;

use function config;
use function sprintf;

class AtlasSearchScoutServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->extend(EngineManager::class, function (EngineManager $engineManager) {
            $engineManager->extend('atlas_search', function ($app) {
                $connectionName = config('scout.atlas_search.connection');
                $connection = $app->make('db')->connection($connectionName);

                if (! $connection instanceof Connection) {
                    throw new InvalidArgumentException(sprintf('The MongoDB connection for Atlas Search must be a MongoDB connection. Got "%s". Set configuration "scout.atlas_search.connection"', $connection->getDriverName()));
                }

                return new AtlasSearchEngine($connection);
            });

            return $engineManager;
        });
    }
}
