<?php namespace Jenssegers\Mongodb\Facades;

use Illuminate\Support\Facades\Facade;

class MongoDB extends Facade {

	/**
	 * Get the registered name of the component.
	 *
	 * @return string
	 */
	protected static function getFacadeAccessor()
	{
		return 'mongodb';
	}

}