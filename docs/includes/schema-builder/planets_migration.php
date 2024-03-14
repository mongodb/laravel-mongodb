<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connect = 'mongodb';

    public function up(): void
    {
        // begin index helpers
        Schema::create('planets', function (Blueprint $collection) {
            $collection->sparse('rings');
            $collection->expire('last_visible_dt', 86400);
        });
        // end index helpers

        // begin multi index helpers
        Schema::create('planet_systems', function (Blueprint $collection) {
            $collection->index('last_visible_dt', null, null, ['sparse' => true, 'expireAfterSeconds' => 3600, 'unique' => true]);
        });
        // end multi index helpers
    }

    public function down(): void
    {
        Schema::drop('planets');
    }
};
