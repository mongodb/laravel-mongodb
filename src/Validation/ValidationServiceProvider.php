<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Validation;

use Illuminate\Validation\ValidationServiceProvider as BaseProvider;
use Override;

class ValidationServiceProvider extends BaseProvider
{
    #[Override]
    protected function registerPresenceVerifier()
    {
        $this->app->singleton('validation.presence', function ($app) {
            return new DatabasePresenceVerifier($app['db']);
        });
    }
}
