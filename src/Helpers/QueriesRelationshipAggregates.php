<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Helpers;

use Closure;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use MongoDB\BSON\Binary;
use MongoDB\Laravel\Eloquent\Model as DocumentModel;
use MongoDB\Laravel\Relations\EmbedsOneOrMany;
use Stringable;

use function bin2hex;
use function class_basename;
use function count;
use function explode;
use function get_debug_type;
use function implode;
use function in_array;
use function is_array;
use function is_scalar;
use function is_string;
use function preg_replace;
use function sprintf;
use function str_starts_with;
use function strtolower;

/**
 * Support for withCount, withExists, withSum, withAvg, withMin and withMax on document models.
 *
 * MongoDB has no correlated subquery, so the values cannot be selected with the parent documents
 * as Eloquent does. They are computed with one additional query per aggregate, after the parent
 * documents are read.
 *
 * @internal
 */
trait QueriesRelationshipAggregates
{
    private const AGGREGATE_FUNCTIONS = ['count', 'exists', 'sum', 'avg', 'min', 'max'];

    /** @var array<string, array{name: string, function: string, column: string, parentKey: ?string, constraints: Closure}> */
    private array $withAggregates = [];

    /** @inheritdoc */
    public function withAggregate($relations, $column, $function = null)
    {
        if (empty($relations)) {
            return $this;
        }

        if (! in_array($function, self::AGGREGATE_FUNCTIONS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Aggregate function "%s" is not supported by MongoDB. Supported functions are: %s.',
                $function ?? 'null',
                implode(', ', self::AGGREGATE_FUNCTIONS),
            ));
        }

        if (! is_string($column)) {
            throw new InvalidArgumentException('The aggregate column name must be a string.');
        }

        if (str_starts_with($column, '$')) {
            throw new InvalidArgumentException(sprintf(
                'The aggregate column name "%s" must not start with "$".',
                $column,
            ));
        }

        foreach ($this->parseWithRelations(is_array($relations) ? $relations : [$relations]) as $name => $constraints) {
            [$name, $alias] = $this->resolveAggregateAlias($name, $function, $column);

            $relation = $this->getRelationWithoutConstraints($name);
            $this->assertAggregateRelationSupported($relation, $name);
            $this->assertEmbeddedConstraintsSupported($relation, $name, $constraints);

            // The key used to match the aggregated values with the parent documents must be read.
            $parentKey = $this->getAggregateParentKey($relation);
            if ($parentKey !== null && $this->getQuery()->columns !== null) {
                $this->addSelect($parentKey);
            }

            $this->withAggregates[$alias] = [
                'name' => $name,
                'function' => $function,
                'column' => $column,
                'parentKey' => $parentKey,
                'constraints' => $constraints,
            ];
        }

        return $this;
    }

    /**
     * Resolve the relation name and the attribute alias, using the same alias as
     * Illuminate\Database\Eloquent\Concerns\QueriesRelationships::withAggregate.
     *
     * @return array{0: string, 1: string} the relation name and the alias
     */
    private function resolveAggregateAlias(string $name, string $function, string $column): array
    {
        $segments = explode(' ', $name);

        if (count($segments) === 3 && strtolower($segments[1]) === 'as') {
            return [$segments[0], $segments[2]];
        }

        $alias = Str::snake(preg_replace(
            '/[^[:alnum:][:space:]_]/u',
            '',
            sprintf('%s %s %s', $name, $function, strtolower($column)),
        ));

        return [$name, $alias];
    }

    private function assertEmbeddedConstraintsSupported(Relation $relation, string $name, Closure $constraints): void
    {
        if (! $relation instanceof EmbedsOneOrMany) {
            return;
        }

        $subQuery = $relation->getRelated()->newQuery();
        $constraints($subQuery);
        $query = $subQuery->getQuery();

        if ($query->wheres || $query->limit !== null || $query->offset !== null || $query->distinct) {
            throw new LogicException(sprintf(
                'Constraints on the embedded relation "%s" are not supported.',
                $name,
            ));
        }
    }

    /** @inheritdoc */
    public function eagerLoadRelations(array $models)
    {
        foreach ($this->withAggregates as $alias => $aggregate) {
            $this->assertAggregateNotUsedInQuery($alias);
            $this->hydrateAggregate($models, $alias, $aggregate);
        }

        return parent::eagerLoadRelations($models);
    }

    /**
     * @param EloquentModel[]                                                                                 $models
     * @param array{name: string, function: string, column: string, parentKey: ?string, constraints: Closure} $aggregate
     */
    private function hydrateAggregate(array $models, string $alias, array $aggregate): void
    {
        // The relation is resolved for each execution to start from a query without constraints.
        $relation = $this->getRelationWithoutConstraints($aggregate['name']);

        // Embedded documents are already part of the parent document, no query is needed.
        if ($relation instanceof EmbedsOneOrMany) {
            foreach ($models as $model) {
                $model->setAttribute($alias, self::aggregateValues(
                    $model->{$aggregate['name']}()->getResults(),
                    $aggregate['function'],
                    $aggregate['column'],
                ));
            }

            return;
        }

        $relation->addEagerConstraints($models);
        // Same as Illuminate\Database\Eloquent\Concerns\QueriesRelationships::withAggregate,
        // the constraints are applied to the query of the related model.
        $aggregate['constraints']($relation->getQuery());

        // HasOneOrMany relations are aggregated by the server, grouped by foreign key.
        if ($relation instanceof HasOneOrMany) {
            $this->hydrateGroupedAggregate($models, $alias, $aggregate, $relation);

            return;
        }

        // The related documents cannot be grouped by the server: a BelongsTo relation has a single
        // related document per parent, and the keys of many-to-many relations are stored in an array
        // field. The eager loading logic is reused to match the related documents to their parent.
        $this->hydrateMatchedAggregate($models, $alias, $aggregate, $relation);
    }

    /**
     * @param EloquentModel[]                                                                                 $models
     * @param array{name: string, function: string, column: string, parentKey: ?string, constraints: Closure} $aggregate
     */
    private function hydrateGroupedAggregate(array $models, string $alias, array $aggregate, HasOneOrMany $relation): void
    {
        $function = $aggregate['function'];
        $foreignKey = $this->getHasCompareKey($relation);
        $results = $relation->getQuery()->toBase()
            ->groupBy($foreignKey)
            ->aggregate($function === 'exists' ? 'count' : $function, [$aggregate['column']]);

        $values = [];
        foreach ($results as $result) {
            $values[self::aggregateKey($result->{$foreignKey})] = $function === 'exists'
                ? $result->aggregate > 0
                : $result->aggregate;
        }

        $default = self::aggregateDefault($function);
        foreach ($models as $model) {
            $key = self::aggregateKey($model->getAttribute($aggregate['parentKey']));

            $model->setAttribute($alias, $values[$key] ?? $default);
        }
    }

    /**
     * @param EloquentModel[]                                                                                 $models
     * @param array{name: string, function: string, column: string, parentKey: ?string, constraints: Closure} $aggregate
     */
    private function hydrateMatchedAggregate(array $models, string $alias, array $aggregate, Relation $relation): void
    {
        $relation->match($models, $relation->getEager(), $alias);

        foreach ($models as $model) {
            // match() leaves the relation unset on models without related documents.
            $related = $model->relationLoaded($alias) ? $model->getRelation($alias) : null;
            $model->unsetRelation($alias);

            $model->setAttribute($alias, self::aggregateValues($related, $aggregate['function'], $aggregate['column']));
        }
    }

    private static function aggregateDefault(string $function): mixed
    {
        return match ($function) {
            'count' => 0,
            'exists' => false,
            default => null,
        };
    }

    private static function aggregateValues(mixed $related, string $function, string $column): mixed
    {
        $values = match (true) {
            $related instanceof Collection => $related,
            $related === null => new Collection(),
            default => new Collection([$related]),
        };

        if ($values->isEmpty()) {
            return self::aggregateDefault($function);
        }

        return match ($function) {
            'count' => $values->count(),
            'exists' => true,
            'sum' => $values->sum($column),
            'avg' => $values->avg($column),
            'min' => $values->min($column),
            'max' => $values->max($column),
        };
    }

    /** Document keys are compared as strings, as ObjectId instances are not identical. */
    private static function aggregateKey(mixed $value): string
    {
        if ($value instanceof Binary) {
            return bin2hex($value->getData());
        }

        if (is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        throw new InvalidArgumentException(sprintf(
            'The relation key of type "%s" cannot be used to match aggregated values.',
            get_debug_type($value),
        ));
    }

    private function assertAggregateRelationSupported(Relation $relation, string $name): void
    {
        if ($relation instanceof EmbedsOneOrMany) {
            return;
        }

        if (! DocumentModel::isDocumentModel($relation->getRelated()) || $this->isAcrossConnections($relation)) {
            throw new LogicException(sprintf(
                'Aggregating the hybrid relation "%s" is not supported. The related model must be stored in MongoDB.',
                $name,
            ));
        }

        if (
            $relation instanceof HasOneOrMany
            || $relation instanceof BelongsToMany
            || ($relation instanceof BelongsTo && ! $relation instanceof MorphTo)
        ) {
            return;
        }

        throw new LogicException(sprintf(
            '%s is not supported for relation aggregates.',
            class_basename($relation),
        ));
    }

    private function getAggregateParentKey(Relation $relation): ?string
    {
        return match (true) {
            $relation instanceof HasOneOrMany => $relation->getLocalKeyName(),
            $relation instanceof BelongsTo => $relation->getForeignKeyName(),
            $relation instanceof BelongsToMany => $relation->getParentKeyName(),
            default => null,
        };
    }

    /** The aggregated value does not exist in the documents, the server cannot use it. */
    private function assertAggregateNotUsedInQuery(string $alias): void
    {
        if (isset($this->getQuery()->orders[$alias])) {
            throw new LogicException(sprintf(
                'Ordering by the aggregated field "%s" is not supported, as it is computed after the documents are read. Sort the results with the collection method "sortBy" instead.',
                $alias,
            ));
        }
    }
}
