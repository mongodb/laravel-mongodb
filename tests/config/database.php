<?php

return [

    'connections' => [

        'mongodb' => [
            'name'       => 'mongodb',
            'driver'     => 'mongodb',
            'host'       => 'mongodb',
            'database'   => 'unittest',
            'port'       => 27017,
        ],

        'mysql' => [
            'driver'    => 'mysql',
            'host'      => 'mysql',
            'database'  => 'unittest',
            'username'  => 'root',
            'password'  => '',
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
        ],
    ],

];
