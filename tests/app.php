<?php
use Jenssegers\Mongodb\Connection;
use Jenssegers\Mongodb\Model;
use Jenssegers\Mongodb\DatabaseManager;
use Jenssegers\Mongodb\Facades\DB;
use Illuminate\Events\Dispatcher;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;

# Fake app
$app = array();

# Event dispatcher
$app['events'] = new Dispatcher;

# Cache driver
$app['cache'] = new Repository(new ArrayStore);

# Database configuration
$app['config']['database.connections']['mongodb'] = array(
	'name'	   => 'mongodb',
	'host'     => 'localhost',
	'database' => 'unittest'
);

# Register service
$app['mongodb'] = new DatabaseManager($app);

# Static setup
Model::setConnectionResolver($app['mongodb']);
DB::setFacadeApplication($app);