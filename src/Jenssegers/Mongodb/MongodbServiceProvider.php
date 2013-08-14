<?php namespace Jenssegers\Mongodb;

use Jenssegers\Mongodb\Model;
use Jenssegers\Mongodb\DatabaseManager;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\DatabasePresenceVerifier;

class MongodbServiceProvider extends ServiceProvider {

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerValidationPresenceVerifier();

        Model::setConnectionResolver($this->app['mongodb']);
        Model::setEventDispatcher($this->app['events']);
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
        $this->app['mongodb'] = $this->app->share(function($app)
        {
            return new DatabaseManager($app);
        });
    }

    /**
     * Register MongoDB as the ConnectionResolverInterface on the DatabasePresenceVerifier.
     * This allows Validation methods which utilize the PresenceVerifierInterface to use our
     * MongoDB connection.
     *
     * @return void
     */
    public function registerValidationPresenceVerifier()
    {
        $this->app['validation.presence'] = $this->app->share(function($app)
        {
            return new DatabasePresenceVerifier($app['mongodb']);
        });
    }

}