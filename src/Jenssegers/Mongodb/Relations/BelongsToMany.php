<?php namespace Jenssegers\Mongodb\Relations;

use Jenssegers\Mongodb\Model;
use Illuminate\Database\Eloquent\Collection;
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
			$this->query->where($this->getForeignKey(), '=', $this->parent->getKey());
		}
	}

	/**
	 * Sync the intermediate tables with a list of IDs or collection of models.
	 *
	 * @param  array  $ids
	 * @param  bool   $detaching
	 * @return void
	 */
	public function sync($ids, $detaching = true)
	{
		if ($ids instanceof Collection) $ids = $ids->modelKeys();

		// First we need to attach any of the associated models that are not currently
		// in this joining table. We'll spin through the given IDs, checking to see
		// if they exist in the array of current ones, and if not we will insert.
		$current = $this->parent->{$this->otherKey} ?: array();

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

		$records = $this->createAttachRecords((array) $id, $attributes);

		// Get the ID's to attach to the two documents
		$otherIds = array_pluck($records, $this->otherKey);
		$foreignIds = array_pluck($records, $this->foreignKey);

		// Attach to the parent model
		$this->parent->push($this->otherKey, $otherIds[0]);

		// Generate a new related query instance
		$query = $this->getNewRelatedQuery();

		// Set contraints on the related query
		$query->where($this->related->getKeyName(), $id);

		// Attach to the related model
		$query->push($this->foreignKey, $foreignIds[0]);

		if ($touch) $this->touchIfTouching();
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

		$query = $this->getNewRelatedQuery();

		// If associated IDs were passed to the method we will only delete those
		// associations, otherwise all of the association ties will be broken.
		// We'll return the numbers of affected rows when we do the deletes.
		$ids = (array) $ids;

		// Pull each id from the parent.
		foreach ($ids as $id)
		{
			$this->parent->pull($this->otherKey, $id);
		}

		// Prepare the query to select all related objects.
		if (count($ids) > 0)
		{
			$query->whereIn($this->related->getKeyName(), $ids);
		}

		// Remove the relation to the parent.
		$query->pull($this->foreignKey, $this->parent->getKey());

		if ($touch) $this->touchIfTouching();

		return count($ids);
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
	 * Get a new related query.
	 *
	 * @return \Illuminate\Database\Query\Builder
	 */
	public function getNewRelatedQuery()
	{
		return $this->related->newQuery();
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
}
