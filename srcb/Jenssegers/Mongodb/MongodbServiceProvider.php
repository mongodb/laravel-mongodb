<?php namespace Jenssegers\Mongodb;

use Jenssegers\Mongodb\Model;
use Jenssegers\Mongodb\ConnectionResolver;
use Illuminate\Support\ServiceProvider;

class MongodbServiceProvider extends ServiceProvider {

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        Model::setConnectionResolver($this->app['db.mongodb']);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // The database manager is used to resolve various connections, since multiple
        // connections might be managed. It also implements the connection resolver
        // interface which may be used by other components requiring connections.
        $this->app['db.mongodb'] = $this->app->share(function($app)
        {
            return new ConnectionResolver($app);
        });
    }

}