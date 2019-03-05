<?php namespace Jenssegers\Mongodb\Auth;
use Auth;
use Jenssegers\Mongodb\Auth\MongoDBUserProvider as MongoDBProvider;
use Illuminate\Support\ServiceProvider;
class AuthServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        Auth::provider('mongodb', function($app, array $config) {
            return new MongoDBProvider();
        });
    }
    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}