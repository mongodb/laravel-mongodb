<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;

return [
    'disks' => [
        'gridfs-connection-minimal' => [
            'driver' => 'gridfs',
            'connection' => 'mongodb',
        ],

        'gridfs-connection-full' => [
            'driver' => 'gridfs',
            'connection' => 'mongodb',
        ],

        'gridfs-bucket-service' => [
            'driver' => 'gridfs',
            'bucket' => 'bucket',
        ],

        'gridfs-bucket-factory' => [
            'driver' => 'gridfs',
            'bucket' => static fn (Application $app) => $app['db']
                ->connection('mongodb')
                ->getMongoDB()
                ->selectGridFSBucket(),
        ],
    ],
];
