<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Auth;

use Illuminate\Auth\Passwords\PasswordBrokerManager as BasePasswordBrokerManager;

class PasswordBrokerManager extends BasePasswordBrokerManager
{
    /** @inheritdoc */
    protected function createTokenRepository(array $config)
    {
        return new DatabaseTokenRepository(
            $this->app['db']->connection(),
            $this->app['hash'],
            $config['table'],
            $this->app['config']['app.key'],
            $config['expire'],
            $config['throttle'] ?? 0,
        );
    }
}
