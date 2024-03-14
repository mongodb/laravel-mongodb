<?php

use Illuminate\Database\Migrations\Migration;
use MongoDB\Laravel\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
 
return new class extends Migration
{

    protected $connect = 'mongodb';

    public function up(): void
    {
      // begin create index
      Schema::create('flights', function (Blueprint $collection) {
          $collection->index('mission_type');
          $collection->index(['launch_location' => 1, 'launch_date' => -1]);
          $collection->unique('mission_id', null, null, [ 'name' => 'unique_mission_id_idx']);
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
