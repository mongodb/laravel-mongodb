<?php

use Illuminate\Database\Migrations\Migration;
use MongoDB\Laravel\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
 
return new class extends Migration
{

    protected $connect = 'mongodb';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
      Schema::create('astronauts', function (Blueprint $collection) {
          $collection->index('name');
      });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('astronauts');
    }
};
