<?php

return [

    'default' => env('QUEUE_CONNECTION'),

    'connections' => [

        'database' => [
            'driver' => 'mongodb',
            'table'  => 'jobs',
            'queue'  => 'default',
            'expire' => 60,
        ],

    ],

    'failed' => [
        'database' => env('MONGO_DATABASE'),
        'table'    => 'failed_jobs',
    ],

];
