<?php namespace Jenssegers\Mongodb\Relations;

class BelongsTo extends \Illuminate\Database\Eloquent\Relations\BelongsTo {

	/**
	* Set the base constraints on the relation query.
	*
	* @return void
	*/
	public function addConstraints()
	{
		if (static::$constraints)
		{
			// The foreign key field may be non-existent in mongodb.
			// In this case, the property $this->parent->{$this->foreignKey} cannot be retrieved
			// and an ErrorException will be thrown.
			// The following try-catch construct avoids the case in which the field doesn't exists.
			try
			{
                $foreign_key = $this->parent->{$this->foreignKey};
            }
            catch (ErrorException $ex)
            {
            	// The property doesn't exists.
            	// Set it to null
                $foreign_key = null;
            }

			// For belongs to relationships, which are essentially the inverse of has one
			// or has many relationships, we need to actually query on the primary key
			// of the related models matching on the foreign key that's on a parent.
			$this->query->where($this->otherKey, '=', $foreign_key);
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
		// We'll grab the primary key name of the related models since it could be set to
		// a non-standard name and not "id". We will then construct the constraint for
		// our eagerly loading query so it returns the proper models from execution.
		$key = $this->otherKey;

		$this->query->whereIn($key, $this->getEagerModelKeys($models));
	}

}
