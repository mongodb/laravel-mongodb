<?php

namespace Jenssegers\Mongodb\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model as EloquentModel;

class BelongsTo extends \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    /**
     * Get the key for comparing against the parent key in "has" query.
     * @return string
     */
    public function getHasCompareKey()
    {
        return $this->getOwnerKey();
    }

    /**
     * @inheritdoc
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            // For belongs to relationships, which are essentially the inverse of has one
            // or has many relationships, we need to actually query on the primary key
            // of the related models matching on the foreign key that's on a parent.
            $this->query
                ->where($this->getOwnerKey(), '=', $this->parent->{$this->foreignKey})
                ->orWhere($this->getOwnerKey().'._id', '=', $this->parent->{$this->foreignKey});
        }
    }

    /**
     * @inheritdoc
     */
    public function addEagerConstraints(array $models)
    {
        // We'll grab the primary key name of the related models since it could be set to
        // a non-standard name and not "id". We will then construct the constraint for
        // our eagerly loading query so it returns the proper models from execution.
        $key = $this->getOwnerKey();

        $this->query->whereIn($key, $this->getEagerModelKeys($models));
    }

    /**
     * @inheritdoc
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        return $query;
    }

    /**
     * Get the owner key with backwards compatible support.
     * @return string
     */
    public function getOwnerKey()
    {
        return property_exists($this, 'ownerKey') ? $this->ownerKey : $this->otherKey;
    }

    /**
     * Get the name of the "where in" method for eager loading.
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string $key
     * @return string
     */
    protected function whereInMethod(EloquentModel $model, $key)
    {
        return 'whereIn';
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param  array  $models
     * @param  \Illuminate\Database\Eloquent\Collection  $results
     * @param  string  $relation
     * @return array
     */
    public function match(array $models, Collection $results, $relation)
    {
        $foreign = $this->foreignKey;
        $owner = $this->ownerKey;
        // First we will get to build a dictionary of the child models by their primary
        // key of the relationship, then we can easily match the children back onto
        // the parents using that dictionary and the primary key of the children.
        $dictionary = [];
        foreach ($results as $result) {
            $dictionary[$result->getAttribute($owner)] = $result;
        }
        // Once we have the dictionary constructed, we can loop through all the parents
        // and match back onto their children using these keys of the dictionary and
        // the primary key of the children to map them onto the correct instances.
        foreach ($models as $model) {
            if (isset($dictionary[(string) $model->{$foreign}])) {
                $model->setRelation($relation, $dictionary[(string) $model->{$foreign}]);
            }
        }
        return $models;
    }
}
