<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use LogicException;
use MongoDB\Laravel\Query\BsonValueKey;
use Override;

use function array_key_last;
use function array_merge;
use function collect;

/**
 * MongoDB has neither joins nor qualified column names, so the intermediate keys
 * of a through relation are read with one query against the through collection,
 * then the related documents are constrained with a "whereIn" on those keys.
 *
 * @internal
 */
trait ResolvesThroughKeys
{
    private ThroughKeyState $throughKeys;

    /** @inheritdoc */
    #[Override]
    public function addConstraints()
    {
        if (! static::$constraints) {
            return;
        }

        $this->constrainByFarParentKeys([$this->farParent->getAttribute($this->localKey)]);
    }

    /** @inheritdoc */
    #[Override]
    public function addEagerConstraints(array $models)
    {
        $this->constrainByFarParentKeys($this->getKeys($models, $this->localKey));
    }

    /**
     * @internal
     *
     * @param ThroughKeyState $state
     *
     * @return $this
     */
    public function restoreThroughKeyState(ThroughKeyState $state)
    {
        $this->throughKeys = $state;

        return $this;
    }

    /**
     * @throws LogicException
     *
     * @inheritdoc
     */
    #[Override]
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        throw new LogicException('Through relations cannot be used in existence queries: Eloquent builds the constraint from a join, which MongoDB does not have. Use has() or whereHas() instead.');
    }

    /**
     * The key matching the related documents to their far parent must be read,
     * as the "laravel_through_key" alias of Eloquent requires a join.
     *
     * @inheritdoc
     */
    #[Override]
    protected function shouldSelect(array $columns = ['*'])
    {
        if ($columns === ['*']) {
            return $columns;
        }

        return array_merge($columns, [$this->secondKey]);
    }

    /**
     * The far parent key of every document matched by the given related query,
     * repeated once per document so that occurrences can be counted.
     *
     * @param Builder $relatedQuery
     *
     * @return Collection
     */
    public function pluckFarParentKeys(Builder $relatedQuery)
    {
        $throughKeys = $relatedQuery->pluck($this->secondKey);

        $farParentKeys = $this->readFarParentKeys($this->secondLocalKey, $throughKeys->all());

        return $throughKeys
            ->map(fn (mixed $throughKey): mixed => self::lookUpFarParentKey($farParentKeys, $throughKey))
            ->reject(static fn (mixed $farParentKey): bool => $farParentKey === null)
            ->values();
    }

    /**
     * @param Model $farParent
     *
     * @return string|null
     */
    public function getFarParentDictionaryKey(Model $farParent)
    {
        return BsonValueKey::of($farParent->getAttribute($this->localKey));
    }

    /**
     * Collect every related document of each far parent, without narrowing to
     * one, which is what a through relation aggregates over.
     *
     * @internal
     *
     * @param Model[] $models
     *
     * @return Model[]
     */
    public function matchAll(array $models, EloquentCollection $results, string $relation)
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $this->getFarParentDictionaryKey($model);

            if ($key === null || ! isset($dictionary[$key])) {
                continue;
            }

            $model->setRelation($relation, $this->related->newCollection($dictionary[$key]));
        }

        return $models;
    }

    /** @inheritdoc */
    #[Override]
    protected function buildDictionary(EloquentCollection $results)
    {
        $dictionary = [];

        foreach ($results as $result) {
            $farParentKey = BsonValueKey::of(self::lookUpFarParentKey(
                $this->throughKeys()->farParentKeyByThroughKey,
                $result->getAttribute($this->secondKey),
            ));

            if ($farParentKey === null) {
                continue;
            }

            $dictionary[$farParentKey][] = $result;
        }

        return $dictionary;
    }

    /** @param list<mixed> $farParentKeys */
    private function constrainByFarParentKeys(array $farParentKeys): void
    {
        $throughParents = $this->readThroughParents($this->firstKey, $farParentKeys);

        $this->throughKeys = $this->throughKeys()->resolvedTo($this->indexFarParentKeys($throughParents));

        $this->constrainRelatedQuery(
            $throughParents->pluck($this->secondLocalKey)->all(),
        );
    }

    /** @param list<mixed> $throughKeys */
    private function constrainRelatedQuery(array $throughKeys): void
    {
        $query = $this->query->getQuery();
        $whereIndex = $this->throughKeys()->whereIndex;

        if ($whereIndex !== null) {
            $query->wheres[$whereIndex]['values'] = $throughKeys;

            return;
        }

        $this->query->whereIn($this->secondKey, $throughKeys);

        $this->throughKeys = $this->throughKeys()->constrainedAt(array_key_last($query->wheres));
    }

    private function throughKeys(): ThroughKeyState
    {
        return $this->throughKeys ??= new ThroughKeyState();
    }

    /**
     * @param list<mixed> $values
     *
     * @return array<string, mixed>
     */
    private function readFarParentKeys(string $column, array $values): array
    {
        return $this->indexFarParentKeys($this->readThroughParents($column, $values));
    }

    /**
     * @param list<mixed> $values
     *
     * @return EloquentCollection
     */
    private function readThroughParents(string $column, array $values)
    {
        $values = $this->distinctKeys($values);

        if ($values === []) {
            return $this->throughParent->newCollection();
        }

        return $this->newThroughQuery()->whereIn($column, $values)->get();
    }

    /**
     * The keys reach the through lookup once per related document, so that
     * occurrences can be counted, but the "where in" needs each of them once.
     *
     * @param list<mixed> $values
     *
     * @return list<mixed>
     */
    private function distinctKeys(array $values): array
    {
        return collect($values)
            ->reject(static fn (mixed $value): bool => $value === null)
            ->unique(static fn (mixed $value): ?string => BsonValueKey::of($value))
            ->values()
            ->all();
    }

    /** @return Builder */
    private function newThroughQuery()
    {
        return $this->throughParent->newQuery()->select([$this->secondLocalKey, $this->firstKey]);
    }

    /** @return array<string, mixed> */
    private function indexFarParentKeys(EloquentCollection $throughParents): array
    {
        $keys = [];

        foreach ($throughParents as $throughParent) {
            $keys += $this->farParentKeyPair($throughParent);
        }

        return $keys;
    }

    /** @return array<string, mixed> */
    private function farParentKeyPair(Model $throughParent): array
    {
        $throughKey = BsonValueKey::of($throughParent->getAttribute($this->secondLocalKey));
        $farParentKey = $throughParent->getAttribute($this->firstKey);

        if ($throughKey === null || $farParentKey === null) {
            return [];
        }

        return [$throughKey => $farParentKey];
    }

    /** @param array<string, mixed> $farParentKeys */
    private static function lookUpFarParentKey(array $farParentKeys, mixed $throughKey): mixed
    {
        $key = BsonValueKey::of($throughKey);

        if ($key === null) {
            return null;
        }

        return $farParentKeys[$key] ?? null;
    }
}
