<?php

namespace Moloquent\Passport;

use Illuminate\Support\ServiceProvider;

class PassportServiceProvider extends ServiceProvider
{
    public function register()
    {
        /*
         * Passport client extends Eloquent model by default, so we alias them.
         */
        $loader = \Illuminate\Foundation\AliasLoader::getInstance();

        $loader->alias('Laravel\Passport\AuthCode', AuthCode::class);
        $loader->alias('Laravel\Passport\Client', Client::class);
        $loader->alias('Laravel\Passport\PersonalAccessClient', PersonalAccessClient::class);
        $loader->alias('Laravel\Passport\Token', Token::class);
    }
}
