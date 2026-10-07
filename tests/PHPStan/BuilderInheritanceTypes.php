<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\PHPStan;

use Illuminate\Database\Eloquent\Builder as LaravelBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use MongoDB\Laravel\Eloquent\Builder as EloquentBuilder;
use MongoDB\Laravel\Tests\Models\Casting;
use MongoDB\Laravel\Tests\Models\User;

use function PHPStan\Testing\assertType;

/**
 * Type assertions pinning the expected PHPStan types for a model's query builders.
 *
 * `User` uses the `DocumentModel` trait on a non-MongoDB base class, `Casting` extends
 * `MongoDB\Laravel\Eloquent\Model` directly, so both construction styles are covered.
 *
 * These functions are never executed: they exist to let PHPStan validate the types.
 */
final class BuilderInheritanceTypes
{
    /** @param EloquentBuilder<User> $builder */
    public static function inheritedTemplateMethodResolvesToBoundModel(EloquentBuilder $builder): void
    {
        assertType(User::class, $builder->make());
    }

    /** @param EloquentBuilder<User> $builder */
    public static function toBaseResolvesToMongoQueryBuilder(EloquentBuilder $builder): void
    {
        assertType('MongoDB\Laravel\Query\Builder', $builder->toBase());
    }

    public static function newQueryResolvesToMongoBuilder(User $user): void
    {
        assertType('MongoDB\Laravel\Eloquent\Builder<MongoDB\Laravel\Tests\Models\User>', $user->newQuery());
    }

    public static function newEloquentBuilderResolvesToMongoBuilder(User $user, QueryBuilder $query): void
    {
        assertType('MongoDB\Laravel\Eloquent\Builder<MongoDB\Laravel\Tests\Models\User>', $user->newEloquentBuilder($query));
    }

    /** @param EloquentBuilder<User> $builder */
    public static function inheritedMethodsResolveBoundModel(EloquentBuilder $builder): void
    {
        assertType(User::class, $builder->create([]));
        assertType(User::class, $builder->getModel());
        assertType(User::class, $builder->newModelInstance());
        assertType('MongoDB\Laravel\Tests\Models\User|null', $builder->first());
        assertType('MongoDB\Laravel\Tests\Models\User', $builder->findOrFail(1));
        assertType('Illuminate\Database\Eloquent\Collection<int, MongoDB\Laravel\Tests\Models\User>', $builder->get());
    }

    /** @param EloquentBuilder<User> $builder */
    public static function inheritedFluentMethodsKeepMongoBuilder(EloquentBuilder $builder): void
    {
        assertType('MongoDB\Laravel\Eloquent\Builder<MongoDB\Laravel\Tests\Models\User>', $builder->where('name', 'x'));
        assertType('MongoDB\Laravel\Eloquent\Builder<MongoDB\Laravel\Tests\Models\User>', $builder->orWhere('name', 'y'));
        assertType('MongoDB\Laravel\Eloquent\Builder<MongoDB\Laravel\Tests\Models\User>', $builder->whereKey('id'));
        assertType('MongoDB\Laravel\Eloquent\Builder<MongoDB\Laravel\Tests\Models\User>', $builder->latest());
    }

    public static function staticQueryResolvesToMongoBuilder(): void
    {
        assertType('MongoDB\Laravel\Eloquent\Builder<MongoDB\Laravel\Tests\Models\User>', User::query());
    }

    public static function siblingQueryFactoriesResolveToMongoBuilder(User $user): void
    {
        assertType('MongoDB\Laravel\Eloquent\Builder<MongoDB\Laravel\Tests\Models\User>', $user->newModelQuery());
        assertType('MongoDB\Laravel\Eloquent\Builder<MongoDB\Laravel\Tests\Models\User>', $user->newQueryWithoutScopes());
    }

    public static function directMongoModelSubclassResolvesMongoBuilder(Casting $model, QueryBuilder $query): void
    {
        assertType('MongoDB\Laravel\Eloquent\Builder<MongoDB\Laravel\Tests\Models\Casting>', $model->newQuery());
        assertType('MongoDB\Laravel\Eloquent\Builder<MongoDB\Laravel\Tests\Models\Casting>', $model->newEloquentBuilder($query));
        assertType('MongoDB\Laravel\Eloquent\Builder<MongoDB\Laravel\Tests\Models\Casting>', Casting::query());
    }

    /** @param EloquentBuilder<User> $builder */
    public static function mongoBuilderIsAssignableToLaravelBuilder(EloquentBuilder $builder): void
    {
        self::acceptsLaravelBuilder($builder);
    }

    /** @param LaravelBuilder<User> $builder */
    private static function acceptsLaravelBuilder(LaravelBuilder $builder): void
    {
    }
}
