<?php

namespace Jenssegers\Mongodb;

use Illuminate\Support\ServiceProvider;
use Jenssegers\Mongodb\Passport\AuthCode;
use Jenssegers\Mongodb\Passport\Client;
use Jenssegers\Mongodb\Passport\PersonalAccessClient;
use Jenssegers\Mongodb\Passport\Token;

class MongodbPassportServiceProvider extends ServiceProvider
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
