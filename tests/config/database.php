<?php

return array(

	'connections' => array(

		'mongodb' => array(
			'name'	   => 'mongodb',
			'driver'   => 'mongodb',
			'host'     => 'localhost',
			'database' => 'unittest',
		),

		'mysql' => array(
			'driver'    => 'mysql',
			'host'      => 'localhost',
			'database'  => 'unittest',
			'username'  => 'travis',
			'password'  => '',
			'charset'   => 'utf8',
			'collation' => 'utf8_unicode_ci',
			'prefix'    => '',
		),
	)

);
