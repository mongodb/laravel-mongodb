<?php

namespace Jenssegers\Mongodb\Auth;

use Illuminate\Auth\Passwords\PasswordBrokerManager as BasePasswordBrokerManager;

class PasswordBrokerManager extends BasePasswordBrokerManager
{
    /**
     * @inheritdoc
     */
    protected function createTokenRepository(array $config)
    {
        return new DatabaseTokenRepository(
            $this->app['db']->connection(),
            $this->app['hash'],
            $config['table'],
            $this->app['config']['app.key'],
            $config['expire']
        );
    }
}
