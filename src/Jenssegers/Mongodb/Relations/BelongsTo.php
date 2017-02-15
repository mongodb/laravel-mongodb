<?php namespace Jenssegers\Mongodb\Relations;

class BelongsTo extends \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    /**
     * Set the base constraints on the relation query.
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            // For belongs to relationships, which are essentially the inverse of has one
            // or has many relationships, we need to actually query on the primary key
            // of the related models matching on the foreign key that's on a parent.
            $this->query->where($this->getOtherKeyPropertyName(), '=', $this->parent->{$this->foreignKey});
        }
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array  $models
     */
    public function addEagerConstraints(array $models)
    {
        // We'll grab the primary key name of the related models since it could be set to
        // a non-standard name and not "id". We will then construct the constraint for
        // our eagerly loading query so it returns the proper models from execution.
        $key = $this->getOtherKeyPropertyName();

        $this->query->whereIn($key, $this->getEagerModelKeys($models));
    }

    /**
     * get the Other/Owner Key name based on different version of Illuminate/Database
     * see commit https://github.com/illuminate/database/commit/6a35698d72e276f435324b7e29b3cd37ef7d5d9c
     * @return string
     */
    public function getOtherKeyPropertyName()
    {
        return property_exists($this, "ownerKey") ? $this->ownerKey : $this->otherKey;
    }
}
