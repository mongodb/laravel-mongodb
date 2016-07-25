<?php namespace Jenssegers\Mongodb\Validation;

use Illuminate\Validation\ValidationServiceProvider;

class MongodbValidationServiceProvider extends ValidationServiceProvider
{
    protected function registerPresenceVerifier()
    {
        $this->app->singleton('validation.presence', function ($app) {
            return new DatabasePresenceVerifier($app['db']);
        });
    }
}
