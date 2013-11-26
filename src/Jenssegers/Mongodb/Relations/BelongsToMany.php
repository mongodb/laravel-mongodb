<?php namespace Jenssegers\Mongodb\Relations;

use Illuminate\Database\Eloquent\Collection;
use Jenssegers\Mongodb\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany as EloquentBelongsToMany;

class BelongsToMany extends EloquentBelongsToMany {

	/**
	 * Hydrate the pivot table relationship on the models.
	 *
	 * @param  array  $models
	 * @return void
	 */
	protected function hydratePivotRelation(array $models)
	{
		// Do nothing
	}

	/**
	 * Set the select clause for the relation query.
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
	 */
	protected function getSelectColumns(array $columns = array('*'))
	{
		return $columns;
	}

	/**
	 * Set the base constraints on the relation query.
	 *
	 * @return void
	 */
	public function addConstraints()
	{
		if (static::$constraints)
		{
			// Make sure that the primary key of the parent
			// is in the relationship array of keys
			$this->query->whereIn($this->foreignKey, array($this->parent->getKey()));
		}
	}

	/**
	 * Sync the intermediate tables with a list of IDs.
	 *
	 * @param  array  $ids
	 * @param  bool   $detaching
	 * @return void
	 */
	public function sync(array $ids, $detaching = true)
	{
		// First we need to attach any of the associated models that are not currently
		// in this joining table. We'll spin through the given IDs, checking to see
		// if they exist in the array of current ones, and if not we will insert.
		$current = $this->parent->{$this->otherKey};

		// Check if the current array exists or not on the parent model and create it
		// if it does not exist
		if (is_null($current)) $current = array();

		$records = $this->formatSyncList($ids);

		$detach = array_diff($current, array_keys($records));

		// Next, we will take the differences of the currents and given IDs and detach
		// all of the entities that exist in the "current" array but are not in the
		// the array of the IDs given to the method which will complete the sync.
		if ($detaching and count($detach) > 0)
		{
			$this->detach($detach);
		}

		// Now we are finally ready to attach the new records. Note that we'll disable
		// touching until after the entire operation is complete so we don't fire a
		// ton of touch operations until we are totally done syncing the records.
		$this->attachNew($records, $current, false);

		$this->touchIfTouching();
	}

	/**
	 * Attach all of the IDs that aren't in the current array.
	 *
	 * @param  array  $records
	 * @param  array  $current
	 * @param  bool   $touch
	 * @return void
	 */
	protected function attachNew(array $records, array $current, $touch = true)
	{
		foreach ($records as $id => $attributes)
		{
			// If the ID is not in the list of existing pivot IDs, we will insert a new pivot
			// record, otherwise, we will just update this existing record on this joining
			// table, so that the developers will easily update these records pain free.
			if ( ! in_array($id, $current))
			{
				$this->attach($id, $attributes, $touch);
			}
		}
	}

	/**
	 * Attach a model to the parent.
	 *
	 * @param  mixed  $id
	 * @param  array  $attributes
	 * @param  bool   $touch
	 * @return void
	 */
	public function attach($id, array $attributes = array(), $touch = true)
	{
		if ($id instanceof Model) $id = $id->getKey();

		// Generate a new parent query instance
		$parent = $this->newParentQuery();

		// Generate a new related query instance
		$related = $this->related->newInstance();

		// Set contraints on the related query
		$related = $related->where($this->related->getKeyName(), $id);

		$records = $this->createAttachRecords((array) $id, $attributes);

		// Get the ID's to attach to the two documents
		$otherIds = array_pluck($records, $this->otherKey);
		$foreignIds = array_pluck($records, $this->foreignKey);

		// Attach to the parent model
		$parent->push($this->otherKey, $otherIds[0])->update(array());

		// Attach to the related model
		$related->push($this->foreignKey, $foreignIds[0])->update(array());
	}

	/**
	 * Create an array of records to insert into the pivot table.
	 *
	 * @param  array  $ids
	 * @return void
	 */
	protected function createAttachRecords($ids, array $attributes)
	{
		$records = array();;

		// To create the attachment records, we will simply spin through the IDs given
		// and create a new record to insert for each ID. Each ID may actually be a
		// key in the array, with extra attributes to be placed in other columns.
		foreach ($ids as $key => $value)
		{
			$records[] = $this->attacher($key, $value, $attributes, false);
		}

		return $records;
	}

	/**
	 * Detach models from the relationship.
	 *
	 * @param  int|array  $ids
	 * @param  bool  $touch
	 * @return int
	 */
	public function detach($ids = array(), $touch = true)
	{
		if ($ids instanceof Model) $ids = (array) $ids->getKey();

		$query = $this->newParentQuery();

		// If associated IDs were passed to the method we will only delete those
		// associations, otherwise all of the association ties will be broken.
		// We'll return the numbers of affected rows when we do the deletes.
		$ids = (array) $ids;

		if (count($ids) > 0)
		{
			$query->whereIn($this->otherKey, $ids);
		}

		if ($touch) $this->touchIfTouching();

		// Once we have all of the conditions set on the statement, we are ready
		// to run the delete on the pivot table. Then, if the touch parameter
		// is true, we will go ahead and touch all related models to sync.
		foreach($ids as $id)
		{
			$query->pull($this->otherKey, $id);
		}

		return count($ids);
	}

	/**
	 * Create a new query builder for the parent
	 *
	 * @return Jenssegers\Mongodb\Builder
	 */
	protected function newParentQuery()
	{
		$query = $this->parent->newQuery();

		return $query->where($this->parent->getKeyName(), '=', $this->parent->getKey());
	}

	/**
	 * Build model dictionary keyed by the relation's foreign key.
	 *
	 * @param  \Illuminate\Database\Eloquent\Collection  $results
	 * @return array
	 */
	protected function buildDictionary(Collection $results)
	{
		$foreign = $this->foreignKey;

		// First we will build a dictionary of child models keyed by the foreign key
		// of the relation so that we will easily and quickly match them to their
		// parents without having a possibly slow inner loops for every models.
		$dictionary = array();

		foreach ($results as $result)
		{
			foreach ($result->$foreign as $single)
			{
				$dictionary[$single][] = $result;
			}
		}

		return $dictionary;
	}

	/**
	 * Get the fully qualified foreign key for the relation.
	 *
	 * @return string
	 */
	public function getForeignKey()
	{
		return $this->foreignKey;
	}

	/**
	 * Get the fully qualified "other key" for the relation.
	 *
	 * @return string
	 */
	public function getOtherKey()
	{
		return $this->otherKey;
	}
}
