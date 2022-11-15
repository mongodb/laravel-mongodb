<?php

return [
    'connections' => [
        'mongodb' => [
            'name' => 'mongodb',
            'driver' => 'mongodb',
            'dsn' => env('MONGODB_URI', 'mongodb://127.0.0.1/'),
            'database' => env('MONGODB_DATABASE', 'unittest'),
            'options' => [
                'connectTimeoutMS'         => 100,
                'serverSelectionTimeoutMS' => 250,
            ],
        ],

        'mysql' => [
            'driver' => 'mysql',
            'host' => env('MYSQL_HOST', 'mysql'),
            'port' => env('MYSQL_PORT') ? (int) env('MYSQL_PORT') : 3306,
            'database' => env('MYSQL_DATABASE', 'unittest'),
            'username' => env('MYSQL_USERNAME', 'root'),
            'password' => env('MYSQL_PASSWORD', ''),
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
        ],
    ],
];
