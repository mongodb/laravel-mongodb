<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Seeder;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserTableSeeder extends Seeder
{
    public function run()
    {
        DB::table('users')->delete();

        DB::table('users')->insert(['name' => 'John Doe', 'seed' => true]);
    }
}
