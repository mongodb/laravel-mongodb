<?php namespace Jenssegers\Mongodb\Eloquent;

use MongoCursor;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class Builder extends EloquentBuilder {

    /**
     * The methods that should be returned from query builder.
     *
     * @var array
     */
    protected $passthru = array(
        'toSql', 'lists', 'insert', 'insertGetId', 'pluck',
        'count', 'min', 'max', 'avg', 'sum', 'exists', 'push', 'pull'
    );

    /**
     * Add the "has" condition where clause to the query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $hasQuery
     * @param  \Illuminate\Database\Eloquent\Relations\Relation  $relation
     * @param  string  $operator
     * @param  int  $count
     * @param  string  $boolean
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function addHasWhere(EloquentBuilder $hasQuery, Relation $relation, $operator, $count, $boolean)
    {
        $query = $hasQuery->getQuery();

        // Get the number of related objects for each possible parent.
        $relationCount = array_count_values($query->lists($relation->getHasCompareKey()));

        // Remove unwanted related objects based on the operator and count.
        $relationCount = array_filter($relationCount, function($counted) use ($count, $operator)
        {
            // If we are comparing to 0, we always need all results.
            if ($count == 0) return true;

            switch ($operator)
            {
                case '>=':
                case '<':
                    return $counted >= $count;
                case '>':
                case '<=':
                    return $counted > $count;
                case '=':
                case '!=':
                    return $counted == $count;
            }
        });

        // If the operator is <, <= or !=, we will use whereNotIn.
        $not = in_array($operator, array('<', '<=', '!='));

        // If we are comparing to 0, we need an additional $not flip.
        if ($count == 0) $not = !$not;

        // All related ids.
        $relatedIds = array_keys($relationCount);

        // Add whereIn to the query.
        return $this->whereIn($this->model->getKeyName(), $relatedIds, $boolean, $not);
    }

    /**
     * Create a raw database expression.
     *
     * @param  closure  $expression
     * @return mixed
     */
    public function raw($expression = null)
	{
		// Get raw results from the query builder.
		$results = $this->query->raw($expression);

		$connection = $this->model->getConnectionName();

		// Convert MongoCursor results to a collection of models.
		if ($results instanceof MongoCursor)
		{
			$results = iterator_to_array($results, false);

			$models = array();

			// Once we have the results, we can spin through them and instantiate a fresh
			// model instance for each records we retrieved from the database. We will
			// also set the proper connection name for the model after we create it.
			foreach ($results as $result)
			{
				$models[] = $model = $this->model->newFromBuilder($result);

				$model->setConnection($connection);
			}

			return $this->model->newCollection($models);
		}

		// The result is a single object.
		else if (is_array($results) and array_key_exists('_id', $results))
		{
			$model = $this->model->newFromBuilder($results);

			$model->setConnection($connection);

			return $model;
		}

		return $results;
	}

}
