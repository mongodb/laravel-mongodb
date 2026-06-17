<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\PHPStan;

use Illuminate\Database\Query\Expression;
use MongoDB\Collection as MongoDBCollection;
use MongoDB\Laravel\Eloquent\Builder as EloquentBuilder;
use MongoDB\Laravel\Query\Builder as QueryBuilder;
use MongoDB\Laravel\Tests\Models\User;

use function PHPStan\Testing\assertType;

/**
 * PHPStan type-level tests for Builder::raw() return and param types.
 * These functions are never executed at runtime — they exist to let PHPStan
 * validate that the @param and @return types on raw() match the declared signatures.
 */
final class BuilderRawTypes
{
    public static function queryBuilderRawNull(QueryBuilder $queryBuilder): void
    {
        assertType('MongoDB\Collection', $queryBuilder->raw());
    }

    public static function queryBuilderRawClosureFind(QueryBuilder $queryBuilder): void
    {
        assertType('MongoDB\Driver\CursorInterface', $queryBuilder->raw(fn (MongoDBCollection $c) => $c->find([])));
    }

    public static function queryBuilderRawClosureFindOne(QueryBuilder $queryBuilder): void
    {
        assertType('array|object|null', $queryBuilder->raw(fn (MongoDBCollection $c) => $c->findOne([])));
    }

    public static function queryBuilderRawExpression(QueryBuilder $queryBuilder): void
    {
        assertType('Illuminate\Database\Query\Expression', $queryBuilder->raw(new Expression('foo')));
    }

    /** @param EloquentBuilder<User> $builder */
    public static function eloquentBuilderRawNull(EloquentBuilder $builder): void
    {
        assertType('MongoDB\Collection', $builder->raw());
    }

    /** @param EloquentBuilder<User> $builder */
    public static function eloquentBuilderRawClosureFind(EloquentBuilder $builder): void
    {
        assertType(
            'Illuminate\Database\Eloquent\Collection<int, MongoDB\Laravel\Tests\Models\User>',
            $builder->raw(fn (MongoDBCollection $c) => $c->find([])),
        );
    }

    /** @param EloquentBuilder<User> $builder */
    public static function eloquentBuilderRawExpression(EloquentBuilder $builder): void
    {
        assertType('Illuminate\Contracts\Database\Query\Expression', $builder->raw(new Expression('foo')));
    }

    /** @param EloquentBuilder<User> $builder */
    public static function eloquentBuilderRawClosureFindOne(EloquentBuilder $builder): void
    {
        // PHPStan simplifies User|Collection<int, User>|array|object|null to array|object|null
        // because object is a supertype of User and Collection.
        assertType(
            'array|object|null',
            $builder->raw(fn (MongoDBCollection $c) => $c->findOne([])),
        );
    }
}
