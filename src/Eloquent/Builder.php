<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Eloquent;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use MongoDB\Driver\Cursor;
use MongoDB\Laravel\Helpers\QueriesRelationships;
use MongoDB\Model\BSONDocument;

use function array_key_exists;
use function array_merge;
use function collect;
use function is_array;
use function iterator_to_array;

/** @method \MongoDB\Laravel\Query\Builder toBase() */
class Builder extends EloquentBuilder
{
    use QueriesRelationships;

    /**
     * The methods that should be returned from query builder.
     *
     * @var array
     */
    protected $passthru = [
        'average',
        'avg',
        'count',
        'dd',
        'doesntexist',
        'dump',
        'exists',
        'getbindings',
        'getconnection',
        'getgrammar',
        'insert',
        'insertgetid',
        'insertorignore',
        'insertusing',
        'max',
        'min',
        'pluck',
        'pull',
        'push',
        'raw',
        'sum',
        'tomql',
    ];

    /** @inheritdoc */
    public function update(array $values, array $options = [])
    {
        // Intercept operations on embedded models and delegate logic
        // to the parent relation instance.
        $relation = $this->model->getParentRelation();
        if ($relation) {
            $relation->performUpdate($this->model, $values);

            return 1;
        }

        return $this->toBase()->update($this->addUpdatedAtColumn($values), $options);
    }

    /** @inheritdoc */
    public function insert(array $values)
    {
        // Intercept operations on embedded models and delegate logic
        // to the parent relation instance.
        $relation = $this->model->getParentRelation();
        if ($relation) {
            $relation->performInsert($this->model, $values);

            return true;
        }

        return parent::insert($values);
    }

    /** @inheritdoc */
    public function insertGetId(array $values, $sequence = null)
    {
        // Intercept operations on embedded models and delegate logic
        // to the parent relation instance.
        $relation = $this->model->getParentRelation();
        if ($relation) {
            $relation->performInsert($this->model, $values);

            return $this->model->getKey();
        }

        return parent::insertGetId($values, $sequence);
    }

    /** @inheritdoc */
    public function delete()
    {
        // Intercept operations on embedded models and delegate logic
        // to the parent relation instance.
        $relation = $this->model->getParentRelation();
        if ($relation) {
            $relation->performDelete($this->model);

            return $this->model->getKey();
        }

        return parent::delete();
    }

    /** @inheritdoc */
    public function increment($column, $amount = 1, array $extra = [])
    {
        // Intercept operations on embedded models and delegate logic
        // to the parent relation instance.
        $relation = $this->model->getParentRelation();
        if ($relation) {
            $value = $this->model->{$column};

            // When doing increment and decrements, Eloquent will automatically
            // sync the original attributes. We need to change the attribute
            // temporary in order to trigger an update query.
            $this->model->{$column} = null;

            $this->model->syncOriginalAttribute($column);

            return $this->model->update([$column => $value]);
        }

        return parent::increment($column, $amount, $extra);
    }

    /** @inheritdoc */
    public function decrement($column, $amount = 1, array $extra = [])
    {
        // Intercept operations on embedded models and delegate logic
        // to the parent relation instance.
        $relation = $this->model->getParentRelation();
        if ($relation) {
            $value = $this->model->{$column};

            // When doing increment and decrements, Eloquent will automatically
            // sync the original attributes. We need to change the attribute
            // temporary in order to trigger an update query.
            $this->model->{$column} = null;

            $this->model->syncOriginalAttribute($column);

            return $this->model->update([$column => $value]);
        }

        return parent::decrement($column, $amount, $extra);
    }

    /** @inheritdoc */
    public function raw($value = null)
    {
        // Get raw results from the query builder.
        $results = $this->query->raw($value);

        // Convert MongoCursor results to a collection of models.
        if ($results instanceof Cursor) {
            $results = iterator_to_array($results, false);

            return $this->model->hydrate($results);
        }

        // Convert MongoDB BSONDocument to a single object.
        if ($results instanceof BSONDocument) {
            $results = $results->getArrayCopy();

            return $this->model->newFromBuilder((array) $results);
        }

        // The result is a single object.
        if (is_array($results) && array_key_exists('_id', $results)) {
            return $this->model->newFromBuilder((array) $results);
        }

        return $results;
    }

    /**
     * Add the "updated at" column to an array of values.
     * TODO Remove if https://github.com/laravel/framework/commit/6484744326531829341e1ff886cc9b628b20d73e
     * will be reverted
     * Issue in laravel/frawework https://github.com/laravel/framework/issues/27791.
     *
     * @return array
     */
    protected function addUpdatedAtColumn(array $values)
    {
        if (! $this->model->usesTimestamps() || $this->model->getUpdatedAtColumn() === null) {
            return $values;
        }

        $column = $this->model->getUpdatedAtColumn();
        $values = array_merge(
            [$column => $this->model->freshTimestampString()],
            $values,
        );

        return $values;
    }

    /** @return ConnectionInterface */
    public function getConnection()
    {
        return $this->query->getConnection();
    }

    /** @inheritdoc */
    protected function ensureOrderForCursorPagination($shouldReverse = false)
    {
        if (empty($this->query->orders)) {
            $this->enforceOrderBy();
        }

        if ($shouldReverse) {
            $this->query->orders = collect($this->query->orders)
                ->map(static fn (int $direction) => $direction === 1 ? -1 : 1)
                ->toArray();
        }

        return collect($this->query->orders)
            ->map(static fn ($direction, $column) => [
                'column' => $column,
                'direction' => $direction === 1 ? 'asc' : 'desc',
            ])->values();
    }
}
