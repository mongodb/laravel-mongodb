<?php namespace Jenssegers\Mongodb\Auth;

use Illuminate\Auth\Passwords\PasswordBrokerManager as BasePasswordBrokerManager;

class PasswordBrokerManager extends BasePasswordBrokerManager
{
    /**
     * Create a token repository instance based on the given configuration.
     *
     * @param  array  $config
     * @return \Illuminate\Auth\Passwords\TokenRepositoryInterface
     */
    protected function createTokenRepository(array $config)
    {
        // temp version check until new dot released for 5.4+
        $version = explode('.', \App::version());

        // Laravel 5.4+
        if ($version[0] >= 5 && $version[1] >= 4) {
            return new DatabaseTokenRepository(
                $this->app['db']->connection(),
                $this->app['hash'],
                $config['table'],
                $this->app['config']['app.key'],
                $config['expire']
            );
        } else {
            // Laravel < v5.4
            return new DatabaseTokenRepository(
                $this->app['db']->connection(),
                $config['table'],
                $this->app['config']['app.key'],
                $config['expire']
            );
        }
    }
}
