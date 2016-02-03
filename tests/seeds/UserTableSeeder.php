<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserTableSeeder extends Seeder
{

    public function run()
    {
        DB::collection('users')->delete();

        DB::collection('users')->insert(['name' => 'John Doe', 'seed' => true]);
    }
}
