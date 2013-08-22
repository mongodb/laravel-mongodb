<?php namespace Jenssegers\Mongodb\Schema;

use Closure;
use Illuminate\Database\Connection;

class Blueprint {

	/**
	 * The MongoCollection object for this blueprint.
	 *
	 * @var MongoCollection
	 */
	protected $collection;

	/**
	 * Create a new schema blueprint.
	 *
	 * @param  string   $table
	 * @param  Closure  $callback
	 * @return void
	 */
	public function __construct(Connection $connection, $collection)
	{
		$this->collection = $connection->getCollection($collection);
	}

	/**
	 * Specify an index for the collection.
	 *
	 * @param  string|array  $columns
	 * @param  array  $otions
	 * @return bool
	 */
	public function index($columns, $options = array())
	{
		$result = $this->collection->ensureIndex($columns, $options);

		return (1 == (int) $result['ok']);
	}

	/**
	 * Indicate that the given index should be dropped.
	 *
	 * @param  string|array  $columns
	 * @return bool
	 */
	public function dropIndex($columns)
	{
		$result = $this->collection->deleteIndex($columns);

		return (1 == (int) $result['ok']);
	}

	/**
	 * Specify a unique index for the collection.
	 *
	 * @param  string|array  $columns
	 * @param  string  $name
	 * @return bool
	 */
	public function unique($columns)
	{
		return $this->index($columns, array("unique" => true));
	}

	/**
	 * Indicate that the given unique key should be dropped.
	 *
	 * @param  string|array  $index
	 * @return bool
	 */
	public function dropUnique($columns)
	{
		return $this->dropIndex($columns);
	}

	/**
	 * Indicate that the collection should be dropped.
	 *
	 * @return bool
	 */
	public function drop()
	{
		$result = $this->collection->drop();

		return (1 == (int) $result['ok']);
	}

}
