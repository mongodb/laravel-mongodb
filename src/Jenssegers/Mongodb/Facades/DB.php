<?php namespace Jenssegers\Mongodb\Facades;

/*
|--------------------------------------------------------------------------
| DEPRECATED
|--------------------------------------------------------------------------
*/

use Illuminate\Support\Facades\Facade;

class DB extends Facade {

	/**
	 * Get the registered name of the component.
	 *
	 * @return string
	 */
	protected static function getFacadeAccessor()
	{
		return 'db';
	}

}