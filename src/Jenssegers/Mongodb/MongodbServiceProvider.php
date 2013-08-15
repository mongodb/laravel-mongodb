<?php namespace Jenssegers\Mongodb;

use Jenssegers\Mongodb\Model;
use Jenssegers\Mongodb\DatabaseManager;
use Illuminate\Support\ServiceProvider;

class MongodbServiceProvider extends ServiceProvider {

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        Model::setConnectionResolver($this->app['db']);
        Model::setEventDispatcher($this->app['events']);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // DEPRECATED
        $this->app['mongodb'] = $this->app->share(function($app)
        {
            return new DatabaseManager($app);
        });

        // Add a mongodb extension to the original database manager
        $this->app['db']->extend('mongodb', function($config)
        {
            return new Connection($config);
        });
    }

}