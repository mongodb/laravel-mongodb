<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    public function up(): void
    {
        $store = Cache::store('mongodb');
        $store->createTTLIndex();
        $store->lock('')->createTTLIndex();
    }
};
