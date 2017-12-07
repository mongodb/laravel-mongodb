<?php

namespace Jenssegers\Mongodb\Eloquent;

use Illuminate\Support\Str;
use Jenssegers\Mongodb\Relations\EmbeddedBy;
use Jenssegers\Mongodb\Relations\EmbedsMany;
use Jenssegers\Mongodb\Relations\EmbedsOne;

trait EmbedsRelations
{
    /**
     * Define an embedded one-to-many relationship.
     *
     * @param  string $related
     * @param  string $localKey
     * @param  string $foreignKey
     * @param  string $relation
     * @return \Jenssegers\Mongodb\Relations\EmbedsMany
     */
    protected function embedsMany($related, $localKey = null, $foreignKey = null, $relation = null)
    {
        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relationships.
        if (is_null($relation)) {
            list(, $caller) = debug_backtrace(false);

            $relation = $caller['function'];
        }

        if (is_null($localKey)) {
            $localKey = $relation;
        }

        if (is_null($foreignKey)) {
            $foreignKey = Str::snake(class_basename($this));
        }

        $query = $this->newQuery();

        $instance = new $related;

        return new EmbedsMany($query, $this, $instance, $localKey, $foreignKey, $relation);
    }

    /**
     * Define an embedded one-to-one relationship.
     *
     * @param  string $related
     * @param  string $localKey
     * @param  string $foreignKey
     * @param  string $relation
     * @return \Jenssegers\Mongodb\Relations\EmbedsOne
     */
    protected function embedsOne($related, $localKey = null, $foreignKey = null, $relation = null)
    {
        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relationships.
        if (is_null($relation)) {
            list(, $caller) = debug_backtrace(false);

            $relation = $caller['function'];
        }

        if (is_null($localKey)) {
            $localKey = $relation;
        }

        if (is_null($foreignKey)) {
            $foreignKey = Str::snake(class_basename($this));
        }

        $query = $this->newQuery();

        $instance = new $related;

        return new EmbedsOne($query, $this, $instance, $localKey, $foreignKey, $relation);
    }

    /**
     * Define an inverse embedded one-to-one relationship.
     *
     * @param  string $related
     * @param  string $localKey
     * @param  string $foreignKey
     * @param  string $relation
     * @return \Jenssegers\Mongodb\Relations\EmbeddedBy
     */
    protected function embeddedBy($related, $foreignKey = null, $ownerKey = null, $relation = null)
    {
        // Use debug backtrace to extract the calling method's name and use that as
        // the relationship name
        list(, $caller) = debug_backtrace(false);
        $relation = $caller['function'];

        $query = $this->parentRelation->getQuery();

        if (is_null($foreignKey)) {
            $foreignKey = Str::snake($relation) . '_id';
        }

        $instance = new $related;

        $ownerKey = $ownerKey ?: $instance->getKeyName();

        return new EmbeddedBy($query, $this, $foreignKey, $ownerKey, $relation);
    }
}
