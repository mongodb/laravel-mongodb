<?php namespace Jenssegers\Mongodb\Auth;

use Jenssegers\Mongodb\Auth\DatabaseTokenRepository as DbRepository;

class PasswordResetServiceProvider extends \Illuminate\Auth\Passwords\PasswordResetServiceProvider {

    /**
     * Register the token repository implementation.
     *
     * @return void
     */
    protected function registerTokenRepository()
    {
        $this->app->singleton('auth.password.tokens', function ($app)
        {
            $connection = $app['db']->connection();

            // The database token repository is an implementation of the token repository
            // interface, and is responsible for the actual storing of auth tokens and
            // their e-mail addresses. We will inject this table and hash key to it.
            $table = $app['config']['auth.password.table'];
            $key = $app['config']['app.key'];
            $expire = $app['config']->get('auth.password.expire', 60);

            return new DbRepository($connection, $table, $key, $expire);
        });
    }

}
