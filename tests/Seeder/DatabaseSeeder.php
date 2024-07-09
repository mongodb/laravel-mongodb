<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Seeder;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(UserTableSeeder::class);
    }
}
