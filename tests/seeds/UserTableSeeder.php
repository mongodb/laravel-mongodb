<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserTableSeeder extends Seeder {

    public function run()
    {
        DB::collection('users')->delete();

        DB::collection('users')->insert(array('name' => 'John Doe', 'seed' => true));
    }
}
