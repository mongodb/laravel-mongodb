<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        // begin create index
        Schema::create('flights', function (Blueprint $collection) {
            $collection->index('mission_type');
            $collection->index(['launch_location' => 1, 'launch_date' => -1]);
            $collection->unique('mission_id', options: ['name' => 'unique_mission_id_idx']);
        });
        // end create index
    }

    public function down(): void
    {
        // begin drop index
        Schema::table('flights', function (Blueprint $collection) {
            $collection->dropIndex('unique_mission_id_idx');
        });
        // end drop index
    }
};
