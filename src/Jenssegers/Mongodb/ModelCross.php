<?php namespace Jenssegers\Mongodb;

use Illuminate\Database\Query\Builder;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Jenssegers\Mongodb\Query\Builder as QueryBuilder;
use Jenssegers\Mongodb\Relations\BelongsTo;
use Jenssegers\Mongodb\Relations\BelongsToMany;

abstract class ModelCross extends \Illuminate\Database\Eloquent\Model {
	
	/**
	 * Define a one-to-one relationship.
	 *
	 * @param  string  $related
	 * @param  string  $foreignKey
	 * @param  string  $localKey
	 * @return \Illuminate\Database\Eloquent\Relations\HasOne
	 */
	public function hasOne($related, $foreignKey = null, $localKey = null)
	{
		$foreignKey = $foreignKey ?: $this->getForeignKey();

		$instance = new $related;

		$localKey = $localKey ?: $this->getKeyName();
		
		if(!($instance instanceof \Jenssegers\Mongodb\Model))
		{
			$foreignKey = $instance->getTable() . '.' . $foreignKey;
		}
		
		return new HasOne($instance->newQuery(), $this, $foreignKey, $localKey);
	}

	/**
	 * Define a one-to-many relationship.
	 *
	 * @param  string  $related
	 * @param  string  $foreignKey
	 * @param  string  $localKey
	 * @return \Illuminate\Database\Eloquent\Relations\HasMany
	 */
	public function hasMany($related, $foreignKey = null, $localKey = null)
	{
		$foreignKey = $foreignKey ?: $this->getForeignKey();

		$instance = new $related;

		$localKey = $localKey ?: $this->getKeyName();
		
		if(!($instance instanceof \Jenssegers\Mongodb\Model))
		{
			$foreignKey = $instance->getTable() . '.' . $foreignKey;
		}

		return new HasMany($instance->newQuery(), $this, $foreignKey, $localKey);
	}

	/**
	 * Define an inverse one-to-one or many relationship.
	 *
	 * @param  string  $related
	 * @param  string  $foreignKey
	 * @param  string  $otherKey
	 * @param  string  $relation
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 */
	public function belongsTo($related, $foreignKey = null, $otherKey = null, $relation = null)
	{
		// If no relation name was given, we will use this debug backtrace to extract
		// the calling method's name and use that as the relationship name as most
		// of the time this will be what we desire to use for the relatinoships.
		if (is_null($relation))
		{
			list(, $caller) = debug_backtrace(false);

			$relation = $caller['function'];
		}

		// If no foreign key was supplied, we can use a backtrace to guess the proper
		// foreign key name by using the name of the relationship function, which
		// when combined with an "_id" should conventionally match the columns.
		if (is_null($foreignKey))
		{
			$foreignKey = snake_case($relation).'_id';
		}

		$instance = new $related;

		// Once we have the foreign key names, we'll just create a new Eloquent query
		// for the related models and returns the relationship instance which will
		// actually be responsible for retrieving and hydrating every relations.
		$query = $instance->newQuery();

		$otherKey = $otherKey ?: $instance->getKeyName();
		
		if($instance instanceof Illuminate\Database\Eloquent\Model)
		{
			return Illuminate\Database\Eloquent\Relations\BelongsTo($query, $this, $foreignKey, $otherKey, $relation);
		}

		return new BelongsTo($query, $this, $foreignKey, $otherKey, $relation);
	}

	/**
	 * Define a many-to-many relationship.
	 *
	 * @param  string  $related
	 * @param  string  $table
	 * @param  string  $foreignKey
	 * @param  string  $otherKey
	 * @param  string  $relation
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
	 */
	public function belongsToMany($related, $table = null, $foreignKey = null, $otherKey = null, $relation = null)
	{
		// If no relationship name was passed, we will pull backtraces to get the
		// name of the calling function. We will use that function name as the
		// title of this relation since that is a great convention to apply.
		if (is_null($relation))
		{
			$caller = $this->getBelongsToManyCaller();

			$name = $caller['function'];
		}

		// First, we'll need to determine the foreign key and "other key" for the
		// relationship. Once we have determined the keys we'll make the query
		// instances as well as the relationship instances we need for this.
		$instance = new $related;
		
		// Now we're ready to create a new query builder for the related model and
		// the relationship instances for the relation. The relations will set
		// appropriate query constraint and entirely manages the hydrations.
		$query = $instance->newQuery();
		
		if($instance instanceof Illuminate\Database\Eloquent\Model)
		{
			$foreignKey = $foreignKey ?: $this->getForeignKey();

			$otherKey = $otherKey ?: $instance->getForeignKey();
			
			// If no table name was provided, we can guess it by concatenating the two
			// models using underscores in alphabetical order. The two model names
			// are transformed to snake case from their default CamelCase also.			
			if (is_null($table))
			{
				$table = $this->joiningTable($related);
			}
			
			return new Illuminate\Database\Eloquent\Relations\BelongsToMany($query, $this, $table, $foreignKey, $otherKey, $relation);
		} 
		else 
		{
			$foreignKey = $foreignKey ?: $this->getForeignKey() . 's';

			$otherKey = $otherKey ?: $instance->getForeignKey() . 's';

			// If no table name was provided, we can guess it by concatenating the two
			// models using underscores in alphabetical order. The two model names
			// are transformed to snake case from their default CamelCase also.
			if (is_null($table))
			{
				$table = $instance->getTable();
			}
			
			return new BelongsToMany($query, $this, $table, $foreignKey, $otherKey, $relation);
		}
	}
	
	/**
     * Get a new query builder instance for the connection.
     *
     * @return Builder
     */
    protected function newBaseQueryBuilder()
    {
		$connection = $this->getConnection();
		
		if($connection instanceof \Jenssegers\Mongodb\Connection)
		{
			return new QueryBuilder($connection);
		}

		$grammar = $connection->getQueryGrammar();
		
        return new Builder($connection, $grammar, $connection->getPostProcessor());
    }
	
	/**
     * Handle dynamic method calls into the method.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return parent::__call($method, $parameters);
    }
	
}