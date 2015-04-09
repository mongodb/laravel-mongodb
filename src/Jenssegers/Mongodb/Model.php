<?php namespace Jenssegers\Mongodb;

abstract class Model extends \Illuminate\Database\Eloquent\Model {

	use \Jenssegers\Mongodb\Mongo;

	/**
	 * The collection associated with the model.
	 *
	 * @var string
	 */
	protected $collection;

}
