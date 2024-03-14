<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connect = 'mongodb';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // begin create geospatial index
        Schema::create('spaceports', function (Blueprint $collection) {
            $collection->geospatial('launchpad_location', '2dsphere');
            $collection->geospatial('runway_location', '2d');
        });
        // end create geospatial index
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('spaceports');
    }
};
