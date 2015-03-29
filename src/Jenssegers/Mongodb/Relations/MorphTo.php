<?php namespace Jenssegers\Mongodb\Relations;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo as EloquentMorphTo;

class MorphTo extends EloquentMorphTo {

	/**
	* Set the base constraints on the relation query.
	*
	* @return void
	*/
	public function addConstraints()
	{
		if (static::$constraints)
		{
			// For belongs to relationships, which are essentially the inverse of has one
			// or has many relationships, we need to actually query on the primary key
			// of the related models matching on the foreign key that's on a parent.
			$this->query->where($this->otherKey, '=', $this->parent->{$this->foreignKey});
		}
	}

}
