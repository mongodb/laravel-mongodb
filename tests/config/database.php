<?php

return array(

	'fetch' => PDO::FETCH_CLASS,
	'default' => 'mongodb',

	'connections' => array(
		'mongodb' => array(
			'name'	   => 'mongodb',
			'driver'   => 'mongodb',
			'host'     => 'localhost',
			'database' => 'unittest',
		),
	)

);
