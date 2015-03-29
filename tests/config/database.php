<?php

return [

	'connections' => [

		'mongodb' => [
			'name'	   => 'mongodb',
			'driver'   => 'mongodb',
			'host'     => 'localhost',
			'database' => 'unittest',
		],

		'mysql' => [
			'driver'    => 'mysql',
			'host'      => 'localhost',
			'database'  => 'unittest',
			'username'  => 'travis',
			'password'  => '',
			'charset'   => 'utf8',
			'collation' => 'utf8_unicode_ci',
			'prefix'    => '',
		],
	]

];
