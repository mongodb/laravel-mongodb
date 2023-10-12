<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MorphToMany extends BelongsToMany
{
    protected $morphType;

    protected $morphClass;

    /**
     * Create a new morph to many relationship instance.
     *
     * @param  Builder     $query
     * @param  Model       $parent
     * @param  string      $name
     * @param  string      $table
     * @param  string      $foreignPivotKey
     * @param  string      $relatedPivotKey
     * @param  string      $parentKey
     * @param  string      $relatedKey
     * @param  string|null $relationName
     * @param  bool        $inverse
     *
     * @return void
     */
    public function __construct(
        Builder $query,
        Model $parent,
        $name,
        $table,
        $foreignPivotKey,
        $relatedPivotKey,
        $parentKey,
        $relatedKey,
        $relationName = null,
        protected $inverse = false,
    ) {
        $this->morphType  = $name . '_type';
        $this->morphClass = $inverse ? $query->getModel()->getMorphClass() : $parent->getMorphClass();

        parent::__construct(
            $query,
            $parent,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey,
            $relationName,
        );
    }

    /**
     * Attach a model to the parent.
     *
     * @param  mixed $id
     * @param  array $attributes
     * @param  bool  $touch
     *
     * @return void
     */
    public function attach($id, array $attributes = [], $touch = true)
    {
        if ($id instanceof Model) {
            $model = $id;

            $id = $model->getKey();

            // Attach the new parent id to the related model.
            $model->push($this->table, [
                $this->foreignPivotKey => $this->parent->getKey(),
                $this->morphType => $this->parent instanceof Model ? $this->parent->getMorphClass() : null,
            ], true);
        } else {
            if ($id instanceof Collection) {
                $id = $id->modelKeys();
            }

            $query = $this->newRelatedQuery();

            $query->whereIn($this->related->getKeyName(), (array) $id);

            // Attach the new parent id to the related model.
            $query->push($this->table, [
                $this->foreignPivotKey => $this->parent->getKey(),
                $this->morphType => $this->parent instanceof Model ? $this->parent->getMorphClass() : null,
            ], true);
        }

        // Attach the new ids to the parent model.
        $this->parent->push($this->relatedPivotKey, (array) $id, true);

        if ($touch) {
            $this->touchIfTouching();
        }
    }

    /** @inheritdoc */
    public function detach($ids = [], $touch = true)
    {
        if ($ids instanceof Model) {
            $ids = (array) $ids->getKey();
        }

        $query = $this->newRelatedQuery();

        // If associated IDs were passed to the method we will only delete those
        // associations, otherwise all of the association ties will be broken.
        // We'll return the numbers of affected rows when we do the deletes.
        $ids = (array) $ids;

        // Detach all ids from the parent model.
        $this->parent->pull($this->relatedPivotKey, $ids);

        // Prepare the query to select all related objects.
        if (count($ids) > 0) {
            $query->whereIn($this->related->getKeyName(), $ids);
        }

        // Remove the relation to the parent.
        // $query->pull($this->foreignPivotKey, $this->foreignPivotKey, $this->parent->getKey());
        $query->pull($this->table, [
            $this->foreignPivotKey => $this->parent->getKey(),
            $this->morphType => $this->parent instanceof Model ? $this->parent->getMorphClass() : null,
        ]);

        if ($touch) {
            $this->touchIfTouching();
        }

        return count($ids);
    }


    /**
     * Get the foreign key "type" name.
     *
     * @return string
     */
    public function getMorphType()
    {
        return $this->morphType;
    }

    /**
     * Get the class name of the parent model.
     *
     * @return string
     */
    public function getMorphClass()
    {
        return $this->morphClass;
    }

    /**
     * Get the indicator for a reverse relationship.
     *
     * @return bool
     */
    public function getInverse()
    {
        return $this->inverse;
    }

    /**
     * Set the where clause for the relation query.
     *
     * @return $this
     */
    protected function setWhere()
    {
        $foreign = $this->getForeignKey();

        if ($this->getInverse()) {
            $this->query->where($foreign, '=', $this->parent->getKey());
        } else {
            $relatedModels = $this->parent->{$this->relatedPivotKey} ?? [];
            $this->query->whereIn($this->relatedKey, $relatedModels);
        }

        return $this;
    }
}
