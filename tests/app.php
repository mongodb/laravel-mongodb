<?php
use Jenssegers\Mongodb\Connection;
use Jenssegers\Mongodb\Model;
use Jenssegers\Mongodb\DatabaseManager;
use Jenssegers\Mongodb\Facades\Mongo;
use Illuminate\Events\Dispatcher;

# Fake app
$app = array();

$app['events'] = new Dispatcher;

# Database configuration
$app['config']['database.connections']['mongodb'] = array(
	'host'     => 'localhost',
	'database' => 'unittest'
);

# Register service
$app['mongodb'] = new DatabaseManager($app);

# Static setup
Model::setConnectionResolver(new DatabaseManager($app));
Mongo::setFacadeApplication($app);