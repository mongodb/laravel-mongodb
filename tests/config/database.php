<?php

return [

    'connections' => [

        'mongodb' => [
            'name'       => 'mongodb',
            'driver'     => 'mongodb',
            'host'       => '127.0.0.1',
            'database'   => 'mongo',
        ],

        'mysql' => [
            'driver'    => 'mysql',
            'host'      => '127.0.0.1',
            'database'  => 'mongo',
            'username'  => 'homestead',
            'password'  => 'secret',
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
        ],
    ],

];
