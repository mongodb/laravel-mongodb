<?php

use Illuminate\Database\Migrations\Migration;
use MongoDB\Laravel\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
 
return new class extends Migration
{

    protected $connect = 'mongodb';

    public function up(): void
    {
        // begin index options
        Schema::create('passengers', function(Blueprint $collection) {    
            $collection->index('last_name', 'passengers_collation_idx', null, [
                'collation' => [ 'locale' => 'de@collation=phonebook', 'numericOrdering' => true ]]);
        });
        // end index options
    }

    public function down(): void
    {
        Schema::drop('passengers');
    }
};
