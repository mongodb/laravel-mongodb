<?php

namespace Jenssegers\Mongodb\Auth;

use Illuminate\Support\ServiceProvider;

class PassportServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Passport models
        $models = [
            \Laravel\Passport\Token::class,
            \Laravel\Passport\Client::class,
            \Laravel\Passport\AuthCode::class,
            \Laravel\Passport\PersonalAccessClient::class,
        ];

        foreach ($models as $model) {
            $this->app->when($model)
                ->needs(\Illuminate\Database\Eloquent\Model::class)
                ->give(\Jenssegers\Mongodb\Eloquent\Model::class);
        }
    }
}
