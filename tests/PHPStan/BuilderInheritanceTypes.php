<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\PHPStan;

use MongoDB\Laravel\Eloquent\Builder as EloquentBuilder;
use MongoDB\Laravel\Tests\Models\User;

use function PHPStan\Testing\assertType;

/**
 * Regression test for the missing `@extends` binding on `Builder<TModel>`.
 *
 * `Builder` declares its own `@template TModel of Model` but, until now, never bound it
 * to its parent's `@template TModel of \Illuminate\Database\Eloquent\Model` via `@extends`.
 * Without that binding, PHPStan has no way to substitute this class's own TModel into
 * methods inherited unmodified from the parent (e.g. `make()`, `findOrFail()`, `firstOrFail()`,
 * ...) — it falls back to the parent's own upper bound, `Illuminate\Database\Eloquent\Model`,
 * discarding the concrete model type.
 *
 * In consumers whose static analysis walks the inheritance chain to resolve the type of
 * a model's query builder (e.g. Larastan's model-builder resolver), this same gap causes
 * every instance to be treated as a plain `Illuminate\Database\Eloquent\Builder` instead
 * of `MongoDB\Laravel\Eloquent\Builder`, making every Mongo-only builder method
 * (`project()`, `unset()`, `hint()`, `options()`, ...) unresolvable there, even though they
 * all work correctly at runtime.
 *
 * These functions are never executed — they exist to let PHPStan validate that the
 * generic binding is actually in effect.
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
}
