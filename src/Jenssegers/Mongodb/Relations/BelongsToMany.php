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
	 * @param  mixed  $ids
	 * @param  bool   $detaching
	 * @return array
	 */
	public function sync($ids, $detaching = true)
	{
		$changes = array(
			'attached' => array(), 'detached' => array(), 'updated' => array()
		);

		if ($ids instanceof Collection) $ids = $ids->modelKeys();

		// First we need to attach any of the associated models that are not currently
		// in this joining table. We'll spin through the given IDs, checking to see
		// if they exist in the array of current ones, and if not we will insert.
		$current = $this->parent->{$this->otherKey} ?: array();

		// See issue #256.
		if ($current instanceof Collection) $current = $ids->modelKeys();

		$records = $this->formatSyncList($ids);

		$detach = array_diff($current, array_keys($records));

		// Next, we will take the differences of the currents and given IDs and detach
		// all of the entities that exist in the "current" array but are not in the
		// the array of the IDs given to the method which will complete the sync.
		if ($detaching and count($detach) > 0)
		{
			$this->detach($detach);

			$changes['detached'] = (array) array_map('intval', $detach);
		}

		// Now we are finally ready to attach the new records. Note that we'll disable
		// touching until after the entire operation is complete so we don't fire a
		// ton of touch operations until we are totally done syncing the records.
		$changes = array_merge(
			$changes, $this->attachNew($records, $current, false)
		);

		if (count($changes['attached']) || count($changes['updated']))
		{
			$this->touchIfTouching();
		}

		return $changes;
	}

	/**
	 * Update an existing pivot record on the table.
	 *
	 * @param  mixed  $id
	 * @param  array  $attributes
	 * @param  bool   $touch
	 * @return void
	 */
	public function updateExistingPivot($id, array $attributes, $touch = true)
	{
		// TODO
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

		// Get the ids to attach to the parent and related model.
		$otherIds = array_pluck($records, $this->otherKey);
		$foreignIds = array_pluck($records, $this->foreignKey);

		// Attach the new ids to the parent model.
		$this->parent->push($this->otherKey, $otherIds, true);

		// Generate a new related query instance.
		$query = $this->newRelatedQuery();

		// Set contraints on the related query.
		$query->where($this->related->getKeyName(), $id);

		// Attach the new ids to the related model.
		$query->push($this->foreignKey, $foreignIds, true);

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

		$query = $this->newRelatedQuery();

		// If associated IDs were passed to the method we will only delete those
		// associations, otherwise all of the association ties will be broken.
		// We'll return the numbers of affected rows when we do the deletes.
		$ids = (array) $ids;

		// Detach all ids from the parent model.
		$this->parent->pull($this->otherKey, $ids);

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
	 * Create a new query builder for the related model.
	 *
	 * @return \Illuminate\Database\Query\Builder
	 */
	protected function newPivotQuery()
	{
		return $this->newRelatedQuery();
	}

	/**
	 * Create a new query builder for the related model.
	 *
	 * @return \Illuminate\Database\Query\Builder
	 */
	public function newRelatedQuery()
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
