# Queue Driver

## `config/queue.php`

```php
<?php

return [
    'default' => env('QUEUE_CONNECTION', 'mongodb'),

    'connections' => [
        'mongodb' => [
            'driver'       => 'mongodb',
            'connection'   => 'mongodb',  // name from config/database.php
            'collection'   => 'jobs',
            'queue'        => 'default',
            'retry_after'  => 90,
            'after_commit' => false,      // set true when dispatching inside DB::transaction()
        ],
    ],

    'failed' => [
        'driver'   => 'mongodb',
        'database' => 'mongodb',
        'table'    => 'failed_jobs',  // use 'table' not 'collection' — Laravel reads this key
    ],

    // Job batching — requires MongoDBBusServiceProvider
    'batching' => [
        'database'   => 'mongodb',
        'collection' => 'job_batches',
    ],
];
```

Register `MongoDB\Laravel\MongoDBBusServiceProvider::class` in `bootstrap/providers.php` for job batching.

## Required indexes

```php
Schema::connection('mongodb')->create('jobs', function (Blueprint $collection): void {
    $collection->index(['queue' => 1, 'reserved' => 1, 'available_at' => 1]);
    $collection->index('reserved_at');
});

Schema::connection('mongodb')->create('failed_jobs', function (Blueprint $collection): void {
    $collection->unique('uuid');
    $collection->index('failed_at');
});
```

## Dispatch and worker

```php
IndexPostJob::dispatch((string) $post->_id)
    ->onConnection('mongodb')
    ->onQueue('indexing');
```

```
php artisan queue:work mongodb --queue=indexing
php artisan queue:failed
php artisan queue:retry all
```

## Job class

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class IndexPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    public function __construct(public readonly string $postId) {}
    // Pass string IDs, never ObjectId instances — they must serialize cleanly.

    public function handle(): void
    {
        // ...
    }
}
```
