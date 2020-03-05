<?php

$mongoHost = env('MONGO_HOST', 'mongodb');
$mongoPort = env('MONGO_PORT') ? (int) env('MONGO_PORT') : 27017;
$mysqlPort = env('MYSQL_PORT') ? (int) env('MYSQL_PORT') : 3306;

return [

    'connections' => [

        'mongodb' => [
            'name' => 'mongodb',
            'driver' => 'mongodb',
            'host' => $mongoHost,
            'database' => env('MONGO_DATABASE', 'unittest'),
        ],

        'dsn_mongodb' => [
            'driver' => 'mongodb',
            'dsn' => "mongodb://$mongoHost:$mongoPort",
            'database' => env('MONGO_DATABASE', 'unittest'),
        ],

        'dsn_mongodb_db' => [
            'driver' => 'mongodb',
            'dsn' => "mongodb://$mongoHost:$mongoPort/".env('MONGO_DATABASE', 'unittest'),
        ],

        'mysql' => [
            'driver' => 'mysql',
            'host' => env('MYSQL_HOST', 'mysql'),
            'port' => $mysqlPort,
            'database' => env('MYSQL_DATABASE', 'unittest'),
            'username' => env('MYSQL_USERNAME', 'root'),
            'password' => env('MYSQL_PASSWORD', ''),
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
        ],
    ],

];
