<?php

declare(strict_types=1);

return [

    'default' => env('QUEUE_CONNECTION'),

    'connections' => [

        'database' => [
            'driver' => 'mongodb',
            'table' => 'jobs',
            'queue' => 'default',
            'expire' => 60,
        ],

    ],

    'failed' => [
        'database' => env('MONGODB_DATABASE'),
        'driver' => 'mongodb',
        'table' => 'failed_jobs',
    ],

];
