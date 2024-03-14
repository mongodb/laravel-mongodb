<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connect = 'mongodb';

    public function up(): void
    {
        // begin conditional create
        $hasCollection = Schema::hasCollection('stars');

        if ($hasCollection) {
            Schema::create('telescopes');
        }
        // end conditional create
    }

    public function down(): void
    {
        Schema::drop('stars');
    }
};
