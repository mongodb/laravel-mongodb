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
            'username' => env('MONGO_USER'),
            'password' => env('MONGO_PASS'),
            'database' => env('MONGO_DATABASE', 'unittest'),
        ],

        'dsn_mongodb' => [
            'driver' => 'mongodb',
            'dsn' => "mongodb://".env('MONGO_USER').":".env('MONGO_PASS') ."@$mongoHost:$mongoPort/admin",
            'database' => env('MONGO_DATABASE', 'unittest'),
        ],

        'dsn_mongodb_db' => [
            'driver' => 'mongodb',
            'dsn' => "mongodb://".env('MONGO_USER').":".env('MONGO_PASS') ."@$mongoHost:$mongoPort/".env('MONGO_DATABASE', 'unittest') . "?authSource=admin",
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
