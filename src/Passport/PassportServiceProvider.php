<?php

namespace Moloquent\Passport;

use Illuminate\Support\ServiceProvider;

class PassportServiceProvider extends ServiceProvider
{
    public function register()
    {
        /*
         * Passport client extends Eloquents model by default, so we alias them.
         */
        $loader = \Illuminate\Foundation\AliasLoader::getInstance();
        $loader->alias('Laravel\Passport\Client', Client::class);
        $loader->alias('Laravel\Passport\PersonalAccessClient', PersonalAccessClient::class);
    }

}
