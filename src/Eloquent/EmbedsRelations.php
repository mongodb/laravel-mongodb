<?php

namespace MongoDB\Laravel\Eloquent;

use Illuminate\Support\Str;
use MongoDB\Laravel\Relations\EmbedsMany;
use MongoDB\Laravel\Relations\EmbedsOne;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Embeds relations for MongoDB models.
 */
trait EmbedsRelations
{
    /**
     * @var array<class-string, array<string, bool>>
     */
    private static array $hasEmbeddedRelation = [];

    /**
     * Define an embedded one-to-many relationship.
     *
     * @param class-string $related
     * @param string|null $localKey
     * @param string|null $foreignKey
     * @param string|null $relation
     * @return EmbedsMany
     */
    protected function embedsMany($related, $localKey = null, $foreignKey = null, $relation = null)
    {
        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relationships.
        if ($relation === null) {
            $relation = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];
        }

        if ($localKey === null) {
            $localKey = $relation;
        }

        if ($foreignKey === null) {
            $foreignKey = Str::snake(class_basename($this));
        }

        $query = $this->newQuery();

        $instance = new $related;

        return new EmbedsMany($query, $this, $instance, $localKey, $foreignKey, $relation);
    }

    /**
     * Define an embedded one-to-many relationship.
     *
     * @param class-string $related
     * @param string|null $localKey
     * @param string|null $foreignKey
     * @param string|null $relation
     * @return EmbedsOne
     */
    protected function embedsOne($related, $localKey = null, $foreignKey = null, $relation = null)
    {
        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relationships.
        if ($relation === null) {
            $relation = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];
        }

        if ($localKey === null) {
            $localKey = $relation;
        }

        if ($foreignKey === null) {
            $foreignKey = Str::snake(class_basename($this));
        }

        $query = $this->newQuery();

        $instance = new $related;

        return new EmbedsOne($query, $this, $instance, $localKey, $foreignKey, $relation);
    }

    /**
     * Determine if an attribute is an embedded relation.
     *
     * @param string $key
     * @return bool
     * @throws \ReflectionException
     */
    private function hasEmbeddedRelation(string $key): bool
    {
        if (! method_exists($this, $method = Str::camel($key))) {
            return false;
        }

        if (isset(self::$hasEmbeddedRelation[static::class][$key])) {
            return self::$hasEmbeddedRelation[static::class][$key];
        }

        $returnType = (new ReflectionMethod($this, $method))->getReturnType();

        return self::$hasEmbeddedRelation[static::class][$key] = $returnType instanceof ReflectionNamedType
            && in_array($returnType->getName(), [EmbedsOne::class, EmbedsMany::class], true);
    }
}
