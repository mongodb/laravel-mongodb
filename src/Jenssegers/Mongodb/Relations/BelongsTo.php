<?php namespace Jenssegers\Mongodb\Relations;

class BelongsTo extends \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    /**
     * @inheritdoc
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            // For belongs to relationships, which are essentially the inverse of has one
            // or has many relationships, we need to actually query on the primary key
            // of the related models matching on the foreign key that's on a parent.
            $this->query->where($this->getOwnerKey(), '=', $this->parent->{$this->foreignKey});
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
     * get the Other/Owner Key name based on different version of Illuminate/Database
     * see commit https://github.com/illuminate/database/commit/6a35698d72e276f435324b7e29b3cd37ef7d5d9c
     * @return string
     */
    public function getOwnerKey()
    {
        return property_exists($this, "ownerKey") ? $this->ownerKey : $this->otherKey;
    }
}
