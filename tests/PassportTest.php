<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class PassportTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        DB::collection('oauth_access_tokens')->delete();
        DB::collection('oauth_access_tokens')->delete();
        DB::collection('oauth_clients')->delete();
        DB::collection('oauth_personal_access_clients')->delete();
        DB::collection('oauth_refresh_tokens')->delete();
    }

    public function testPassportInstall()
    {
        $result = Artisan::call('passport:install', []);
        $this->assertEquals(0, $result);
    }
}
