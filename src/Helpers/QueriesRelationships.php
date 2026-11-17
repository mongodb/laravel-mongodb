<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Helpers;

use Closure;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use LogicException;
use MongoDB\BSON\Binary;
use MongoDB\BSON\ObjectId;
use MongoDB\Laravel\Connection;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Query\BsonValueKey;
use MongoDB\Laravel\Query\Builder as QueryBuilder;
use MongoDB\Laravel\Relations\EmbedsMany;
use MongoDB\Laravel\Relations\EmbedsOneOrMany;
use MongoDB\Laravel\Relations\MorphToMany;
use MongoDB\Laravel\Relations\ThroughRelation;

use function array_key_first;
use function array_map;
use function array_values;
use function assert;
use function class_basename;
use function collect;
use function ctype_xdigit;
use function implode;
use function in_array;
use function is_string;
use function method_exists;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function strlen;
use function substr;

trait QueriesRelationships
{
    private const COUNT_OPERATORS = ['=', '!=', '<>', '<', '<=', '>', '>='];

    private const LOGICAL_OPERATORS = ['$and', '$or', '$nor'];

    private bool $matchesInsideEmbeddedDocument = false;

    private static int $embeddedCountSequence = 0;

    /**
     * Add a relationship count / exists condition to the query.
     *
     * @param Relation|string $relation
     * @param string          $operator
     * @param int             $count
     * @param string          $boolean
     *
     * @return Builder|static
     *
     * @throws Exception
     */
    public function has($relation, $operator = '>=', $count = 1, $boolean = 'and', ?Closure $callback = null)
    {
        if (is_string($relation)) {
            if (str_contains($relation, '.')) {
                return $this->hasNested($relation, $operator, $count, $boolean, $callback);
            }

            $relation = $this->getRelationWithoutConstraints($relation);
        }

        if ($relation instanceof EmbedsOneOrMany) {
            return $this->addEmbeddedHas($relation, $operator, $count, $boolean, $callback);
        }

        // If this is a hybrid relation then we can not use a normal whereExists() query that relies on a subquery
        // We need to use a `whereIn` query
        if (Model::isDocumentModel($this->getModel()) || $this->isAcrossConnections($relation)) {
            return $this->addHybridHas($relation, $operator, $count, $boolean, $callback);
        }

        // If we only need to check for the existence of the relation, then we can optimize
        // the subquery to only run a "where exists" clause instead of this full "count"
        // clause. This will make these queries run much faster compared with a count.
        $method = $this->canUseExistsForExistenceCheck($operator, $count)
            ? 'getRelationExistenceQuery'
            : 'getRelationExistenceCountQuery';

        $hasQuery = $relation->{$method}(
            $relation->getRelated()->newQuery(),
            $this
        );

        // Next we will call any given callback as an "anonymous" scope so they can get the
        // proper logical grouping of the where clauses if needed by this Eloquent query
        // builder. Then, we will be ready to finalize and return this query instance.
        if ($callback) {
            $hasQuery->callScope($callback);
        }

        return $this->addHasWhere(
            $hasQuery,
            $relation,
            $operator,
            $count,
            $boolean,
        );
    }

    private function addEmbeddedHas(EmbedsOneOrMany $relation, string $operator, int $count, string $boolean, ?Closure $callback): static
    {
        if ($relation->getBaseQuery()->wheres !== []) {
            throw new LogicException(sprintf(
                'The embedded relation %s carries constraints, which apply to the parent document rather than to the embedded documents. Pass the relation name and constrain the embedded documents in the callback instead.',
                class_basename($relation),
            ));
        }

        $exists = $this->canUseExistsForExistenceCheck($operator, $count);
        if (! $exists) {
            self::assertCountOperatorSupported($operator);
        }

        $query = self::embeddedQuery($relation, $callback);
        $constraints = self::embeddedFilter($query);

        if ($constraints === null) {
            return $this->whereEmbeddedCount($relation, $query, $operator, $count, $boolean);
        }

        if ($exists) {
            $filter = self::embeddedExistsFilter($relation, $constraints);

            return $this->whereRaw($operator === '<' ? ['$nor' => [$filter]] : $filter, [], $boolean);
        }

        if ($relation instanceof EmbedsMany && $constraints === []) {
            return $this->whereRaw(self::embeddedSizeFilter($relation, $operator, $count), [], $boolean);
        }

        return $this->whereEmbeddedCount($relation, $query, $operator, $count, $boolean);
    }

    private static function embeddedQuery(EmbedsOneOrMany $relation, ?Closure $callback): QueryBuilder
    {
        $query = $relation->getRelated()
            ->newInstance()
            ->setConnection($relation->getParent()->getConnectionName())
            ->newQuery();
        if ($query instanceof self) {
            $query->matchesInsideEmbeddedDocument = true;
        }

        if ($callback) {
            $query->callScope($callback);
        }

        $base = $query->toBase();
        assert($base instanceof QueryBuilder, 'Unexpected query type returned from the Eloquent builder, expecting ' . QueryBuilder::class);
        $base->wheres = self::prefixedWheres($base->wheres, $relation->getLocalKeyName(), self::keyConversion($base));
        self::assertOptionsApplyInsideTheParent($relation, $base);

        return $base;
    }

    /** @return array<string, mixed>|null */
    private static function embeddedFilter(QueryBuilder $query): ?array
    {
        $command = $query->toMql();

        return isset($command['find']) ? (array) ($command['find'][0] ?? []) : null;
    }

    private static function assertOptionsApplyInsideTheParent(EmbedsOneOrMany $relation, QueryBuilder $query): void
    {
        if (! empty($query->offset)) {
            throw new LogicException(sprintf(
                'An "offset()" on the constraints of the embedded relation %s is not supported, because the embedded documents are matched inside the parent document. Filter them with "where()" instead.',
                class_basename($relation),
            ));
        }

        if (isset($query->options['collation'])) {
            throw new LogicException(sprintf(
                'A collation on the constraints of the embedded relation %s is not supported, because the embedded documents are matched with the collation of the parent query. Set the collation on the parent query instead.',
                class_basename($relation),
            ));
        }
    }

    private static function assertCountOperatorSupported(string $operator): void
    {
        if (in_array($operator, self::COUNT_OPERATORS, true)) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'The operator "%s" is not supported to count the documents of an embedded relation. Supported operators are: %s.',
            $operator,
            implode(', ', self::COUNT_OPERATORS),
        ));
    }

    /**
     * @param array<string, mixed> $constraints
     *
     * @return array<string, mixed>
     */
    private static function embeddedExistsFilter(EmbedsOneOrMany $relation, array $constraints): array
    {
        $field = $relation->getLocalKeyName();

        if ($relation instanceof EmbedsMany) {
            return $constraints === []
                ? [$field . '.0' => ['$exists' => true]]
                : [$field => ['$elemMatch' => self::elementConditions($constraints, $field . '.')]];
        }

        if ($constraints === []) {
            return [$field => ['$type' => 'object']];
        }

        return ['$and' => [[$field => ['$type' => 'object']], $constraints]];
    }

    /** @return array<string, mixed> */
    private static function embeddedSizeFilter(EmbedsMany $relation, string $operator, int $count): array
    {
        $field = $relation->getLocalKeyName();

        return match ($operator) {
            '>=' => self::atLeast($field, $count),
            '>' => self::atLeast($field, $count + 1),
            '<' => ['$nor' => [self::atLeast($field, $count)]],
            '<=' => ['$nor' => [self::atLeast($field, $count + 1)]],
            '=' => self::exactly($field, $count),
            '!=', '<>' => ['$nor' => [self::exactly($field, $count)]],
        };
    }

    /** @return array<string, mixed> */
    private static function atLeast(string $field, int $count): array
    {
        if ($count <= 0) {
            return ['$or' => [[$field => ['$exists' => true]], [$field => ['$exists' => false]]]];
        }

        return [$field . '.' . ($count - 1) => ['$exists' => true]];
    }

    /** @return array<string, mixed> */
    private static function exactly(string $field, int $count): array
    {
        return match (true) {
            $count < 0 => ['$nor' => [self::atLeast($field, 0)]],
            $count === 0 => ['$nor' => [self::atLeast($field, 1)]],
            default => [$field => ['$size' => $count]],
        };
    }

    private function whereEmbeddedCount(EmbedsOneOrMany $relation, QueryBuilder $related, string $operator, int $count, string $boolean): static
    {
        if ($this->matchesInsideEmbeddedDocument) {
            throw new LogicException(sprintf(
                'Counting the documents of the embedded relation %s inside another embedded document is not supported. Check for a matching embedded document with whereHas() or whereDoesntHave() instead.',
                class_basename($relation),
            ));
        }

        $query = $this->getQuery();
        assert($query instanceof QueryBuilder, 'Unexpected query type of the parent model, expecting ' . QueryBuilder::class);

        $field = sprintf('__count_%s_%d', $relation->getLocalKeyName(), self::$embeddedCountSequence++);
        $alias = $field . '_documents';
        $lookup = RelationLookup::ofEmbeddedDocuments($query, $alias, [...self::embeddedDocumentStages($relation, $related), ['$count' => 'count']]);

        $query->addInternalStage($lookup, neededForCount: false)
            ->addInternalStage(['$addFields' => [$field => ['$ifNull' => [['$arrayElemAt' => ['$' . $alias . '.count', 0]], 0]]]], neededForCount: false)
            ->addInternalFieldToUnset($alias)
            ->addInternalFieldToUnset($field);

        return $this->where($field, $operator, $count, $boolean);
    }

    /** @return list<array<string, mixed>> */
    private static function embeddedDocumentStages(EmbedsOneOrMany $relation, QueryBuilder $query): array
    {
        $path = '$' . $relation->getLocalKeyName();
        $filter = self::leadingFilter($query);

        return [
            ['$unwind' => $path],
            ...($filter === [] ? [] : [['$match' => $filter]]),
            ['$replaceRoot' => ['newRoot' => $path]],
            ...self::stagesAfterTheFilter($query),
        ];
    }

    /** @return array<string, mixed> */
    private static function leadingFilter(QueryBuilder $query): array
    {
        $command = $query->toMql();

        if (isset($command['find'])) {
            return (array) ($command['find'][0] ?? []);
        }

        if (! isset($command['aggregate'])) {
            throw new LogicException(sprintf(
                'Constraints producing a "%1$s" command are not supported to count the documents of an embedded relation. Remove the %1$s() call and constrain them with "where()" instead.',
                (string) array_key_first($command),
            ));
        }

        $first = $command['aggregate'][0][0] ?? [];

        return $query->wheres !== [] && isset($first['$match']) ? (array) $first['$match'] : [];
    }

    /** @return list<array<string, mixed>> */
    private static function stagesAfterTheFilter(QueryBuilder $query): array
    {
        $withoutFilter = clone $query;
        $withoutFilter->wheres = [];
        $command = $withoutFilter->toMql();

        return isset($command['aggregate']) ? array_values((array) $command['aggregate'][0]) : [];
    }

    /** @return Closure(array<string, mixed>): array<string, mixed> */
    private static function keyConversion(QueryBuilder $query): Closure
    {
        $connection = $query->getConnection();
        assert($connection instanceof Connection, 'Unexpected connection type of the embedded relation, expecting ' . Connection::class);

        if ($connection->getRenameEmbeddedIdField()) {
            return static fn (array $where) => $where;
        }

        return static fn (array $where) => $where['column'] === 'id'
            ? self::withConvertedKey($where)
            : $where;
    }

    /**
     * @param array<string, mixed> $where
     *
     * @return array<string, mixed>
     */
    private static function withConvertedKey(array $where): array
    {
        if (isset($where['values'])) {
            $where['values'] = array_map(self::embeddedKey(...), $where['values']);

            return $where;
        }

        if (isset($where['value'])) {
            $where['value'] = self::embeddedKey($where['value']);
        }

        return $where;
    }

    private static function embeddedKey(mixed $id): mixed
    {
        if (is_string($id) && strlen($id) === 24 && ctype_xdigit($id)) {
            return new ObjectId($id);
        }

        if (is_string($id) && strlen($id) === 16 && preg_match('~[^\x20-\x7E\t\r\n]~', $id) > 0) {
            return new Binary($id, Binary::TYPE_UUID);
        }

        return $id;
    }

    /**
     * @param list<array<string, mixed>>                          $wheres
     * @param Closure(array<string, mixed>): array<string, mixed> $convertKey
     *
     * @return list<array<string, mixed>>
     */
    private static function prefixedWheres(array $wheres, string $field, Closure $convertKey): array
    {
        return array_map(static fn (array $where) => self::prefixedWhere($where, $field, $convertKey), $wheres);
    }

    /**
     * @param array<string, mixed>                                $where
     * @param Closure(array<string, mixed>): array<string, mixed> $convertKey
     *
     * @return array<string, mixed>
     */
    private static function prefixedWhere(array $where, string $field, Closure $convertKey): array
    {
        if ($where['type'] === 'Nested') {
            $where['query'] = clone $where['query'];
            $where['query']->wheres = self::prefixedWheres($where['query']->wheres, $field, $convertKey);

            return $where;
        }

        if ($where['type'] === 'raw') {
            $where['sql'] = ['$and' => self::prefixedConditions($where['sql'], $field)];

            return $where;
        }

        if (! isset($where['column'])) {
            throw new LogicException(sprintf(
                'A "%s" where clause is not supported on an embedded relation, because it cannot be scoped to the fields of the embedded document.',
                $where['type'],
            ));
        }

        $where = $convertKey($where);
        $where['column'] = $field . '.' . $where['column'];

        return $where;
    }

    /**
     * @param array<array-key, mixed> $filter
     *
     * @return array<string, mixed>
     */
    private static function elementConditions(array $filter, string $prefix): array
    {
        $conditions = [];

        foreach ($filter as $key => $value) {
            $conditions += self::elementCondition((string) $key, $value, $prefix);
        }

        return $conditions;
    }

    /** @return array<string, mixed> */
    private static function elementCondition(string $key, mixed $value, string $prefix): array
    {
        if (in_array($key, self::LOGICAL_OPERATORS, true)) {
            return [$key => array_map(static fn (array $filter) => self::elementConditions($filter, $prefix), $value)];
        }

        if (! str_starts_with($key, $prefix)) {
            throw new LogicException(sprintf(
                'A "%s" constraint is not supported on an EmbedsMany relation, because MongoDB only evaluates it against the top-level document.',
                $key,
            ));
        }

        return [substr($key, strlen($prefix)) => $value];
    }

    /**
     * @param array<array-key, mixed> $filter
     *
     * @return list<array<string, mixed>>
     */
    private static function prefixedConditions(array $filter, string $field): array
    {
        $conditions = [];

        foreach ($filter as $key => $value) {
            $conditions[] = self::prefixedCondition((string) $key, $value, $field);
        }

        return $conditions;
    }

    /** @return array<string, mixed> */
    private static function prefixedCondition(string $key, mixed $value, string $field): array
    {
        if (in_array($key, self::LOGICAL_OPERATORS, true)) {
            return [$key => array_map(static fn (array $filter) => ['$and' => self::prefixedConditions($filter, $field)], $value)];
        }

        if (str_starts_with($key, '$')) {
            throw new LogicException(sprintf(
                'A "%s" constraint is not supported on an embedded relation, because it cannot be scoped to the fields of the embedded document.',
                $key,
            ));
        }

        return [$field . '.' . $key => $value];
    }

    /** @return bool */
    protected function isAcrossConnections(Relation $relation)
    {
        return $relation->getParent()->getConnectionName() !== $relation->getRelated()->getConnectionName();
    }

    /**
     * Compare across databases.
     *
     * @param string $operator
     * @param int    $count
     * @param string $boolean
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function addHybridHas(Relation $relation, $operator = '>=', $count = 1, $boolean = 'and', ?Closure $callback = null)
    {
        $this->assertHybridRelationSupported($relation);

        $hasQuery = $relation->getQuery();
        if ($callback) {
            $hasQuery->callScope($callback);
        }

        // If the operator is <, <= or !=, we will use whereNotIn.
        $not = in_array($operator, ['<', '<=', '!=']);
        // If we are comparing to 0, we need an additional $not flip.
        if ($count === 0) {
            $not = ! $not;
        }

        $relations = match (true) {
            $relation instanceof MorphToMany => $relation->getInverse() ?
              $this->handleMorphedByMany($hasQuery, $relation) :
              $this->handleMorphToMany($hasQuery, $relation),
            $relation instanceof ThroughRelation => $relation->pluckFarParentKeys($hasQuery),
            default => $hasQuery->pluck($this->getHasCompareKey($relation))
        };

        $relatedIds = $this->getConstrainedRelatedIds($relations, $operator, $count);

        return $this->whereIn($this->getRelatedConstraintKey($relation), $relatedIds, $boolean, $not);
    }

    /**
     * @param Relation $relation
     *
     * @return void
     *
     * @throws Exception
     */
    private function assertHybridRelationSupported(Relation $relation): void
    {
        if (
            $relation instanceof HasOneOrMany
            || $relation instanceof BelongsTo
            || $relation instanceof ThroughRelation
            || ($relation instanceof BelongsToMany && ! $this->isAcrossConnections($relation))
        ) {
            return;
        }

        throw new LogicException(class_basename($relation) . ' is not supported for hybrid query constraints.');
    }

    /**
     * @param Builder  $hasQuery
     * @param Relation $relation
     *
     * @return Collection
     */
    private function handleMorphToMany($hasQuery, $relation)
    {
        // First we select the parent models that have a relation to our related model,
        // Then extracts related model's ids from the pivot column
        $hasQuery->where($relation->getTable() . '.' . $relation->getMorphType(), $relation->getParent()::class);
        $relations = $hasQuery->pluck($relation->getTable());
        $relations = $relation->extractIds($relations->flatten(1)->toArray(), $relation->getForeignPivotKeyName());

        return collect($relations);
    }

    /**
     * @param Builder  $hasQuery
     * @param Relation $relation
     *
     * @return Collection
     */
    private function handleMorphedByMany($hasQuery, $relation)
    {
        $hasQuery->whereNotNull($relation->getForeignPivotKeyName());

        return $hasQuery->pluck($relation->getForeignPivotKeyName())->flatten(1);
    }

    /** @return string */
    protected function getHasCompareKey(Relation $relation)
    {
        if (method_exists($relation, 'getHasCompareKey')) {
            return $relation->getHasCompareKey();
        }

        return $relation instanceof HasOneOrMany ? $relation->getForeignKeyName() : $relation->getOwnerKeyName();
    }

    /**
     * @param Collection $relations
     * @param string     $operator
     * @param int        $count
     *
     * @return array
     */
    protected function getConstrainedRelatedIds($relations, $operator, $count)
    {
        $ids = $relations instanceof Collection ? $relations->flatten() : collect($relations);

        return $ids
            ->groupBy(static fn (mixed $id): ?string => BsonValueKey::of($id))
            ->filter(fn (Collection $occurrences): bool => $this->occurrenceMatches($occurrences->count(), $operator, $count))
            ->map(static fn (Collection $occurrences): mixed => $occurrences->first())
            ->values()
            ->all();
    }

    private function occurrenceMatches(int $occurrences, string $operator, int $count): bool
    {
        if ($count === 0) {
            return true;
        }

        return match ($operator) {
            '>=', '<' => $occurrences >= $count,
            '>', '<=' => $occurrences > $count,
            '=', '!=' => $occurrences === $count,
            default => false,
        };
    }

    /**
     * Returns key we are constraining this parent model's query with.
     *
     * @return string
     *
     * @throws Exception
     */
    protected function getRelatedConstraintKey(Relation $relation)
    {
        $this->assertHybridRelationSupported($relation);

        if ($relation instanceof HasOneOrMany || $relation instanceof ThroughRelation) {
            return $relation->getLocalKeyName();
        }

        if ($relation instanceof BelongsTo) {
            return $relation->getForeignKeyName();
        }

        if ($relation instanceof BelongsToMany) {
            return $this->model->getKeyName();
        }

        throw new Exception(class_basename($relation) . ' is not supported for hybrid query constraints.');
    }
}
