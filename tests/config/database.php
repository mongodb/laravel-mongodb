<?php

$mongoHost = env('MONGO_HOST', 'mongodb');
$mongoPort = env('MONGO_PORT') ? (int) env('MONGO_PORT') : 27017;

return [

    'connections' => [

        'mongodb' => [
            'name' => 'mongodb',
            'driver' => 'mongodb',
            'host' => $mongoHost,
            'database' => env('MONGO_DATABASE', 'unittest'),
        ],

        'mongodb_replset' => [
            'driver' => 'mongodb',
            'host' => [
                'mongo1:27017',
                'mongo2:27017',
                'mongo3:27017',
            ],
            'database' => env('MONGO_DATABASE', 'unittest'),
            'options'  => [
                'replicaSet' => 'rs0'
            ]
        ],

        'dsn_mongodb' => [
            'driver' => 'mongodb',
            'dsn' => "mongodb://$mongoHost:$mongoPort",
            'database' => env('MONGO_DATABASE', 'unittest'),
        ],

        'mysql' => [
            'driver' => 'mysql',
            'host' => env('MYSQL_HOST', 'mysql'),
            'database' => env('MYSQL_DATABASE', 'unittest'),
            'username' => env('MYSQL_USERNAME', 'root'),
            'password' => env('MYSQL_PASSWORD', ''),
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
        ],
    ],

];
