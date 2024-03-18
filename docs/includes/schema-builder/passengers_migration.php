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
        // begin index options
        Schema::create('passengers', function (Blueprint $collection) {
            $collection->index(
                'last_name',
                name: 'passengers_collation_idx',
                options: [
                    'collation' => [ 'locale' => 'de@collation=phonebook', 'numericOrdering' => true ],
                ],
            );
        });
        // end index options
    }

    public function down(): void
    {
        Schema::drop('passengers');
    }
};
