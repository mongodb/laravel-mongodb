<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Helpers;

use Closure;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use MongoDB\Collection;
use MongoDB\Laravel\Query\Builder as QueryBuilder;
use MongoDB\Laravel\Relations\EmbedsOneOrMany;
use Override;
use ReflectionFunction;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

use function array_is_list;
use function assert;
use function class_basename;
use function collect;
use function in_array;
use function is_a;
use function is_string;
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

        if ($models === [] || ! $relation instanceof EmbedsOneOrMany || ! $this->acceptsTheBuilder($constraints)) {
            return parent::eagerLoadRelation($models, $name, $constraints);
        }

        $query = self::embeddedQuery($relation, $constraints);
        self::assertConstraintsApplyPerParent($relation, $query);

        if (! self::narrowsTheDocuments($query)) {
            return parent::eagerLoadRelation($models, $name, static fn () => null);
        }

        self::assertParentsAreStored($models, $relation);

        return $this->withMatchingDocuments($models, $name, $this->matchingDocumentsPerParent($models, $relation, $query));
    }

    private function acceptsTheBuilder(Closure $constraints): bool
    {
        $combined = (new ReflectionFunction($constraints))->getStaticVariables()['constraints'] ?? [$constraints];

        return collect($combined)->every(fn (Closure $constraint): bool => $this->typeAcceptsTheBuilder(((new ReflectionFunction($constraint))->getParameters()[0] ?? null)?->getType()));
    }

    private function typeAcceptsTheBuilder(?ReflectionType $type): bool
    {
        return match (true) {
            $type === null => true,
            $type instanceof ReflectionUnionType => collect($type->getTypes())->contains(fn (ReflectionType $member) => $this->typeAcceptsTheBuilder($member)),
            $type instanceof ReflectionIntersectionType => collect($type->getTypes())->every(fn (ReflectionType $member) => $this->typeAcceptsTheBuilder($member)),
            $type instanceof ReflectionNamedType && $type->isBuiltin() => in_array($type->getName(), ['mixed', 'object'], true),
            $type instanceof ReflectionNamedType => is_a($this, $type->getName()),
            default => false,
        };
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

        $key = (string) $model->getKey();

        return collect($documents)->contains(static fn (array $document) => (string) ($document[$model->getKeyName()] ?? $document['_id'] ?? $document['id'] ?? '') === $key);
    }

    /** @return array<string, mixed> */
    private function aggregationOptions(): array
    {
        $options = [...$this->mongoQuery()->options, 'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']];

        return $this->mongoQuery()->inheritConnectionOptions($options);
    }

    /**
     * @param list<Model> $models
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function matchingDocumentsPerParent(array $models, EmbedsOneOrMany $relation, QueryBuilder $query): array
    {
        $keyField = self::parentKeyField($models[0]);
        $keys = $this->parentKeys($models, $keyField);

        if ($keys === []) {
            return [];
        }

        $path = '$' . $relation->getLocalKeyName();
        $filter = self::leadingFilter($query);

        $stages = [
            ['$match' => [$keyField => ['$in' => $keys]]],
            ['$project' => [$relation->getLocalKeyName() => 1, $keyField => 1]],
            ['$unwind' => $path],
            ...($filter === [] ? [] : [['$match' => $filter]]),
            ['$replaceRoot' => ['newRoot' => ['parent' => '$' . $keyField, 'document' => $path]]],
            ...(empty($query->orders) ? [] : [['$sort' => self::sortFields($relation, $query->orders)]]),
        ];

        $cursor = $this->mongoQuery()->raw(fn (Collection $collection) => $collection->aggregate($stages, $this->aggregationOptions()));
        $grammar = $this->mongoQuery()->getGrammar();
        $documents = [];

        foreach (iterator_to_array($cursor) as $row) {
            $documents[self::aggregateKey($row['parent'])][] = $grammar->prepareFieldsForResult($row['document'], false);
        }

        return $documents;
    }

    /**
     * @param list<Model> $models
     *
     * @return list<mixed>
     */
    private function parentKeys(array $models, string $keyField): array
    {
        $keys = [];

        foreach ($models as $model) {
            $key = self::storedKey($model);

            if ($key !== null) {
                $keys[] = $keyField === '_id' && is_string($key) ? $this->mongoQuery()->convertKey($key) : $key;
            }
        }

        return $keys;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $matches
     *
     * @return list<array<string, mixed>>
     */
    private static function matchesOfParent(array $matches, Model $parent): array
    {
        $key = self::storedKey($parent);

        return $key === null ? [] : $matches[self::aggregateKey($key)] ?? [];
    }

    private static function storedKey(Model $model): mixed
    {
        $attributes = $model->getAttributes();

        return $attributes[$model->getKeyName()] ?? $attributes['id'] ?? $attributes['_id'] ?? null;
    }

    private static function parentKeyField(Model $parent): string
    {
        return RelationLookup::resolveField($parent, $parent->getKeyName());
    }

    /**
     * @param array<string, int> $orders
     *
     * @return array<string, int>
     */
    private static function sortFields(EmbedsOneOrMany $relation, array $orders): array
    {
        $sort = [];

        foreach ($orders as $field => $direction) {
            $sort['document.' . RelationLookup::resolveField($relation->getParent(), (string) $field, root: false)] = $direction;
        }

        return $sort;
    }

    /**
     * @param list<Model>                               $models
     * @param array<string, list<array<string, mixed>>> $matches
     *
     * @return list<Model>
     */
    private function withMatchingDocuments(array $models, string $name, array $matches): array
    {
        foreach ($models as $model) {
            $model->setRelation($name, $model->{$name}()->hydrate(self::matchesOfParent($matches, $model)));
        }

        return $models;
    }
}
