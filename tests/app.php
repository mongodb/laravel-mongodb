<?php

$app = array();
$app['events'] = new \Illuminate\Events\Dispatcher;
$app['config']['database.connections']['mongodb'] = array(
	'host'     => 'localhost',
	'database' => 'unittest'
);