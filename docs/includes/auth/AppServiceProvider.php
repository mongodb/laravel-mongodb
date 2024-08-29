<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use MongoDB\Laravel\Passport\AuthCode;
use MongoDB\Laravel\Passport\Client;
use MongoDB\Laravel\Passport\PersonalAccessClient;
use MongoDB\Laravel\Passport\RefreshToken;
use MongoDB\Laravel\Passport\Token;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Passport::useAuthCodeModel(AuthCode::class);
        Passport::useClientModel(Client::class);
        Passport::usePersonalAccessClientModel(PersonalAccessClient::class);
        Passport::useRefreshTokenModel(RefreshToken::class);
        Passport::useTokenModel(Token::class);
    }
}
