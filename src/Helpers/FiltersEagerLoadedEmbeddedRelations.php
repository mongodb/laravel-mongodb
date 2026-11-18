<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Helpers;

use Closure;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use MongoDB\Collection;
use MongoDB\Laravel\Query\Builder as QueryBuilder;
use MongoDB\Laravel\Relations\EmbedsOne;
use MongoDB\Laravel\Relations\EmbedsOneOrMany;
use Override;

use function array_intersect_key;
use function array_is_list;
use function array_key_exists;
use function array_map;
use function assert;
use function class_basename;
use function collect;
use function iterator_to_array;
use function method_exists;
use function sprintf;

/** @internal */
trait FiltersEagerLoadedEmbeddedRelations
{
    /** @inheritdoc */
    #[Override]
    protected function eagerLoadRelation(array $models, $name, Closure $constraints)
    {
        $relation = $this->getRelation($name);

        if ($models === [] || ! $relation instanceof EmbedsOneOrMany) {
            return parent::eagerLoadRelation($models, $name, $constraints);
        }

        $query = self::embeddedQuery($relation, $constraints);
        self::assertConstraintsApplyPerParent($relation, $query);

        if (! self::narrowsTheDocuments($query)) {
            return parent::eagerLoadRelation($models, $name, $constraints);
        }

        self::assertParentsAreStored($models, $relation);

        $matches = $this->matchingPositionsPerParent($models, $relation, $query);

        return $this->withMatchingDocuments($relation->initRelation($models, $name), $name, $matches);
    }

    private static function narrowsTheDocuments(QueryBuilder $query): bool
    {
        return $query->wheres !== [] || ! empty($query->orders);
    }

    private static function assertConstraintsApplyPerParent(EmbedsOneOrMany $relation, QueryBuilder $query): void
    {
        if ($query->limit === null) {
            return;
        }

        throw new LogicException(sprintf(
            'A "limit()" on the eager loaded constraints of the embedded relation %s is not supported, because it would apply to the documents of all the parents together. Filter them with "where()" instead.',
            class_basename($relation),
        ));
    }

    /** @param list<Model> $models */
    private static function assertParentsAreStored(array $models, EmbedsOneOrMany $relation): void
    {
        if (! self::isStoredInItsParent($models[0])) {
            return;
        }

        throw new LogicException(sprintf(
            'Constraints on an eager loaded nested embedded relation are not supported, because the parents of %s are embedded themselves. Filter the nested documents with "whereHas()" or read them from the attribute.',
            class_basename($relation),
        ));
    }

    private static function isStoredInItsParent(Model $model): bool
    {
        assert(method_exists($model, 'getParentRelation'));

        $parentRelation = $model->getParentRelation();

        if (! $parentRelation instanceof EmbedsOneOrMany) {
            return false;
        }

        $stored = $parentRelation->getParent()->getAttributes()[$parentRelation->getLocalKeyName()] ?? [];
        $documents = array_is_list($stored) ? $stored : [$stored];

        return collect($documents)->contains(static fn (array $document) => (string) ($document['_id'] ?? $document['id'] ?? '') === (string) $model->getKey());
    }

    /** @return array<string, mixed> */
    private function aggregationOptions(): array
    {
        $options = ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']];
        $session = $this->mongoQuery()->getConnection()->getSession();

        if ($session !== null) {
            $options['session'] = $session;
        }

        return $options + array_intersect_key($this->mongoQuery()->options, ['collation' => true]);
    }

    /**
     * @param list<Model> $models
     *
     * @return array<string, list<int|null>>
     */
    private function matchingPositionsPerParent(array $models, EmbedsOneOrMany $relation, QueryBuilder $query): array
    {
        $path = '$' . $relation->getLocalKeyName();
        $filter = self::leadingFilter($query);
        $keys = array_map(fn (Model $model) => $this->mongoQuery()->convertKey($model->getKey()), $models);

        $stages = [
            ['$match' => ['_id' => ['$in' => $keys]]],
            ['$project' => [$relation->getLocalKeyName() => 1]],
            ['$unwind' => ['path' => $path, 'includeArrayIndex' => '__position']],
            ...($filter === [] ? [] : [['$match' => $filter]]),
            ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [$path, ['__parent' => '$_id', '__position' => '$__position']]]]],
            ...(empty($query->orders) ? [] : [['$sort' => $this->mongoQuery()->getGrammar()->prepareFieldsForQuery($query->orders, false)]]),
            ['$group' => ['_id' => '$__parent', 'positions' => ['$push' => '$__position']]],
        ];

        $cursor = $this->mongoQuery()->raw(fn (Collection $collection) => $collection->aggregate($stages, $this->aggregationOptions()));

        $positions = [];

        foreach (iterator_to_array($cursor) as $row) {
            $positions[(string) $row['_id']] = $row['positions'];
        }

        return $positions;
    }

    /**
     * @param list<Model>                   $models
     * @param array<string, list<int|null>> $matches
     *
     * @return list<Model>
     */
    private function withMatchingDocuments(array $models, string $name, array $matches): array
    {
        foreach ($models as $model) {
            $key = (string) $model->getKey();

            if (! array_key_exists($key, $matches)) {
                continue;
            }

            $model->setRelation($name, $this->keepPositions($model->{$name}(), $model->{$name}()->getResults(), $matches[$key]));
        }

        return $models;
    }

    /** @param list<int|null> $positions */
    private function keepPositions(EmbedsOneOrMany $relation, mixed $documents, array $positions): mixed
    {
        if ($relation instanceof EmbedsOne) {
            return $documents;
        }

        $all = $documents->values()->all();

        return $relation->getRelated()->newCollection(array_map(static fn (int $position) => $all[$position], $positions));
    }
}
