<?php namespace Jenssegers\Mongodb\Relations;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany as EloquentBelongsToMany;

class BelongsToMany extends EloquentBelongsToMany {

	/**
	 * Execute the query as a "select" statement.
	 *
	 * @param  array  $columns
	 * @return \Illuminate\Database\Eloquent\Collection
	 */
	public function get($columns = array('*'))
	{
		// First we'll add the proper select columns onto the query so it is run with
		// the proper columns. Then, we will get the results and hydrate out pivot
		// models with the result of those columns as a separate model relation.
		$select = $this->getSelectColumns($columns);

		$models = $this->query->addSelect($select)->getModels();

		// If we actually found models we will also eager load any relationships that
		// have been specified as needing to be eager loaded. This will solve the
		// n + 1 query problem for the developer and also increase performance.
		if (count($models) > 0)
		{
			$models = $this->query->eagerLoadRelations($models);
		}

		return $this->related->newCollection($models);
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
	 * Get a paginator for the "select" statement.
	 *
	 * @param  int    $perPage
	 * @param  array  $columns
	 * @return \Illuminate\Pagination\Paginator
	 */
	public function paginate($perPage = null, $columns = array('*'))
	{
		$this->query->addSelect($this->getSelectColumns($columns));

		// When paginating results, we need to add the pivot columns to the query and
		// then hydrate into the pivot objects once the results have been gathered
		// from the database since this isn't performed by the Eloquent builder.
		$pager = $this->query->paginate($perPage, $columns);

		return $pager;
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
	 * Set the constraints for an eager load of the relation.
	 *
	 * @param  array  $models
	 * @return void
	 */
	public function addEagerConstraints(array $models)
	{
		$this->query->whereIn($this->getForeignKey(), $this->getKeys($models));
	}

	/**
	 * Save a new model and attach it to the parent model.
	 *
	 * @param  \Illuminate\Database\Eloquent\Model  $model
	 * @param  array  $joining
	 * @param  bool   $touch
	 * @return \Illuminate\Database\Eloquent\Model
	 */
	public function save(Model $model, array $joining = array(), $touch = true)
	{
		$model->save(array('touch' => false));

		$this->attach($model->getKey(), $joining, $touch);
		
		return $model;
	}

	/**
	 * Create a new instance of the related model.
	 *
	 * @param  array  $attributes
	 * @param  array  $joining
	 * @param  bool   $touch
	 * @return \Illuminate\Database\Eloquent\Model
	 */
	public function create(array $attributes, array $joining = array(), $touch = true)
	{
		$instance = $this->related->newInstance($attributes);

		// Save the new instance before we attach it to other models	
		$instance->save(array('touch' => false));
		
		// Attach to the parent instance
		$this->attach($instance->_id, $attributes, $touch);

		return $instance;
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
	 * Format the sync list so that it is keyed by ID.
	 *
	 * @param  array  $records
	 * @return array
	 */
	protected function formatSyncList(array $records)
	{
		$results = array();

		foreach ($records as $id => $attributes)
		{
			if ( ! is_array($attributes))
			{
				list($id, $attributes) = array($attributes, array());
			}

			$results[$id] = $attributes;
		}

		return $results;
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
	 * If we're touching the parent model, touch.
	 *
	 * @return void
	 */
	public function touchIfTouching()
	{ 
	 	if ($this->touchingParent()) $this->getParent()->touch();

	 	if ($this->getParent()->touches($this->relationName)) $this->touch();
	}

	/**
	 * Determine if we should touch the parent on sync.
	 *
	 * @return bool
	 */
	protected function touchingParent()
	{
		return $this->getRelated()->touches($this->guessInverseRelation());
	}

	/**
	 * Attempt to guess the name of the inverse of the relation.
	 *
	 * @return string
	 */
	protected function guessInverseRelation()
	{
		return strtolower(str_plural(class_basename($this->getParent())));
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
	 * Get the related model's updated at column name.
	 *
	 * @return string
	 */
	public function getRelatedFreshUpdate()
	{
		return array($this->related->getUpdatedAtColumn() => $this->related->freshTimestamp());
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