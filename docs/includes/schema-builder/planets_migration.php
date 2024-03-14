<?php

use Illuminate\Database\Migrations\Migration;
use MongoDB\Laravel\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
 
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
    }

    public function down(): void
    {
        Schema::drop('planets');
    }
};
