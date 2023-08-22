<?php

declare(strict_types=1);

namespace Jenssegers\Mongodb\Tests;

use Illuminate\Support\Facades\Artisan;
use Jenssegers\Mongodb\Tests\Models\User;
use Jenssegers\Mongodb\Tests\Seeder\DatabaseSeeder;
use Jenssegers\Mongodb\Tests\Seeder\UserTableSeeder;

class SeederTest extends TestCase
{
    public function tearDown(): void
    {
        User::truncate();
    }

    public function testSeed(): void
    {
        $seeder = new UserTableSeeder;
        $seeder->run();

        $user = User::where('name', 'John Doe')->first();
        $this->assertTrue($user->seed);
    }

    public function testArtisan(): void
    {
        Artisan::call('db:seed', ['class' => DatabaseSeeder::class]);

        $user = User::where('name', 'John Doe')->first();
        $this->assertTrue($user->seed);
    }
}
