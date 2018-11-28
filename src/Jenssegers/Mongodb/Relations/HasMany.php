<?php

namespace Jenssegers\Mongodb\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany as EloquentHasMany;
use Illuminate\Database\Eloquent\Model as EloquentModel;

class HasMany extends EloquentHasMany
{
    /**
     * Get the plain foreign key.
     *
     * @return string
     */
    public function getForeignKeyName()
    {
        return $this->foreignKey;
    }

    /**
     * Get the plain foreign key.
     *
     * @return string
     */
    public function getPlainForeignKey()
    {
        return $this->getForeignKeyName();
    }

    /**
     * Get the key for comparing against the parent key in "has" query.
     *
     * @return string
     */
    public function getHasCompareKey()
    {
        return $this->getForeignKeyName();
    }

    /**
     * @inheritdoc
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        $foreignKey = $this->getHasCompareKey();

        return $query->select($foreignKey)->where($foreignKey, 'exists', true);
    }

    /**
     * Add the constraints for a relationship count query.
     *
     * @param  Builder $query
     * @param  Builder $parent
     * @return Builder
     */
    public function getRelationCountQuery(Builder $query, Builder $parent)
    {
        $foreignKey = $this->getHasCompareKey();

        return $query->select($foreignKey)->where($foreignKey, 'exists', true);
    }

    /**
     * Add the constraints for a relationship query.
     *
     * @param  Builder $query
     * @param  Builder $parent
     * @param  array|mixed $columns
     * @return Builder
     */
    public function getRelationQuery(Builder $query, Builder $parent, $columns = ['*'])
    {
        $query->select($columns);

        $key = $this->wrap($this->getQualifiedParentKeyName());

        return $query->where($this->getHasCompareKey(), 'exists', true);
    }

    /**
     * Get the name of the "where in" method for eager loading.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @return string
     */
    protected function whereInMethod(EloquentModel $model, $key)
    {
        return 'whereIn';
    }
}
