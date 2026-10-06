<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\PHPStan;

use Illuminate\Database\Query\Builder as QueryBuilder;
use MongoDB\Laravel\Eloquent\Builder as EloquentBuilder;
use MongoDB\Laravel\Tests\Models\User;

use function PHPStan\Testing\assertType;

/**
 * Regression test for three related gaps that each independently cause consumers'
 * static analysis to lose track of the Mongo-specific `Builder`/`Model` types, in favor
 * of their plain Laravel counterparts.
 *
 * 1. `Builder` declares its own `@template TModel of Model` but, until now, never bound it
 *    to its parent's `@template TModel of \Illuminate\Database\Eloquent\Model` via `@extends`.
 *    Without that binding, PHPStan has no way to substitute this class's own TModel into
 *    methods inherited unmodified from the parent (e.g. `make()`, `findOrFail()`,
 *    `firstOrFail()`, ...) — it falls back to the parent's own upper bound,
 *    `Illuminate\Database\Eloquent\Model`, discarding the concrete model type.
 *
 * 2. `Illuminate\Database\Eloquent\Model::newQuery()` (and its siblings) declare their own
 *    `@return \Illuminate\Database\Eloquent\Builder<static>`. `DocumentModel` never
 *    overrode it, so any `$model->newQuery()` call resolves, per that inherited signature,
 *    to the plain Laravel builder — PHPStan never traces into the implementation to see
 *    that `newEloquentBuilder()` is itself overridden.
 *
 * 3. `DocumentModel::newEloquentBuilder()` was documented with `@inheritdoc`, which pulls
 *    the *parent's* declared `@return \Illuminate\Database\Eloquent\Builder<*>` instead of
 *    reflecting its own `return new Builder($query)` body. Consumers whose static analysis
 *    resolves a model's builder type by inspecting `newEloquentBuilder()`'s declared return
 *    type directly (e.g. Larastan's model-builder resolver, used for static/forwarded model
 *    calls like `User::where(...)`) read this and conclude the builder is the plain Laravel
 *    one too.
 *
 * Together, these three gaps made every Mongo-only builder method (`project()`, `unset()`,
 * `hint()`, `options()`, ...) unresolvable in such consumers, even though they all work
 * correctly at runtime — the methods are genuinely present, just invisible to PHPStan.
 *
 * `User` is used as the fixture here specifically because it follows the documented
 * "extending a non-MongoDB base class" pattern (`extends Model` + `use DocumentModel`,
 * see resources/boost/skills/laravel-mongodb/references/eloquent-models.md) rather than
 * extending `MongoDB\Laravel\Eloquent\Model` directly, so these assertions also double as
 * regression coverage for that pattern specifically.
 *
 * These functions are never executed — they exist to let PHPStan validate that the
 * generic binding and return types are actually in effect.
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
}
