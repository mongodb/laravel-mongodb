<?php namespace Jenssegers\Mongodb\Eloquent;

use MongoCursor;

class Builder extends \Illuminate\Database\Eloquent\Builder {

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
