<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Helpers;

use BadMethodCallException;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use LogicException;
use MongoDB\Laravel\Query\Builder as QueryBuilder;
use MongoDB\Laravel\Relations\EmbedsOneOrMany;
use Override;
use ReflectionFunction;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

use function array_key_exists;
use function array_pop;
use function assert;
use function class_basename;
use function collect;
use function explode;
use function in_array;
use function is_a;
use function method_exists;
use function sprintf;
use function str_contains;

/** @internal */
trait FiltersEagerLoadedEmbeddedRelations
{
    /** @var array<string, ?string> */
    private array $narrowedEmbeddedRelations = [];

    /** @inheritdoc */
    #[Override]
    protected function eagerLoadRelation(array $models, $name, Closure $constraints)
    {
        $relation = $this->getRelation($name);

        if ($models === [] || ! $relation instanceof EmbedsOneOrMany || ! $this->acceptsTheBuilder($constraints)) {
            return parent::eagerLoadRelation($models, $name, $constraints);
        }

        if (array_key_exists($name, $this->narrowedEmbeddedRelations)) {
            return $this->withNarrowedDocuments($models, $name, $this->narrowedEmbeddedRelations[$name]);
        }

        if (! self::narrowsTheDocuments(self::embeddedQuery($relation, $constraints))) {
            return parent::eagerLoadRelation($models, $name, static fn () => null);
        }

        self::assertNotNestedEmbedding($models, $relation);

        return $this->withReloadedDocuments($models, $name, $constraints);
    }

    private function narrowEagerLoadedEmbeddedRelations(QueryBuilder $query, bool $selectionIsNarrowed): void
    {
        foreach ($this->eagerLoad as $name => $constraints) {
            if (str_contains($name, '.')) {
                $this->assertNestedEmbeddedConstraintsAreSupported($name, $constraints);
                continue;
            }

            $this->narrowEagerLoadedEmbeddedRelation($query, $name, $constraints, $selectionIsNarrowed);
        }
    }

    private function assertNestedEmbeddedConstraintsAreSupported(string $path, Closure $constraints): void
    {
        $model = $this->getModel();
        $names = explode('.', $path);
        $target = array_pop($names);
        $underEmbedded = false;

        foreach ($names as $name) {
            $relation = self::relationNamed($model, $name);

            if ($relation === null) {
                return;
            }

            $underEmbedded = $underEmbedded || $relation instanceof EmbedsOneOrMany;
            $model = $relation->getRelated();
        }

        $relation = self::relationNamed($model, $target);

        if ($underEmbedded && $relation instanceof EmbedsOneOrMany && $this->narrowsTheDocumentsOf($relation, $constraints)) {
            throw new LogicException(sprintf(
                'Constraints on the embedded relation "%s" are not supported, because it is nested in another embedded relation. Constrain the documents after loading them.',
                $path,
            ));
        }
    }

    private function narrowsTheDocumentsOf(EmbedsOneOrMany $relation, Closure $constraints): bool
    {
        return $this->acceptsTheBuilder($constraints) && self::narrowsTheDocuments(self::embeddedQuery($relation, $constraints));
    }

    private static function relationNamed(Model $model, string $name): ?Relation
    {
        try {
            $relation = Relation::noConstraints(static fn () => $model->{$name}());
        } catch (BadMethodCallException) {
            return null;
        }

        return $relation instanceof Relation ? $relation : null;
    }

    private function narrowEagerLoadedEmbeddedRelation(QueryBuilder $query, string $name, Closure $constraints, bool $selectionIsNarrowed): void
    {
        $relation = $this->getRelation($name);

        if (! $relation instanceof EmbedsOneOrMany || ! $this->acceptsTheBuilder($constraints)) {
            return;
        }

        $embedded = self::embeddedQuery($relation, $constraints);

        $this->narrowedEmbeddedRelations[$name] = self::narrowsTheDocuments($embedded)
            ? $this->narrowEmbeddedDocuments($query, $relation, $embedded, '__eager_' . $name, $selectionIsNarrowed)
            : null;
    }

    private function narrowEmbeddedDocuments(QueryBuilder $query, EmbedsOneOrMany $relation, QueryBuilder $embedded, string $alias, bool $selectionIsNarrowed): string
    {
        if ($query->groups) {
            throw new LogicException(sprintf(
                'Constraints on the embedded relation %s are not supported on a grouped query, because a grouped document does not carry the embedded documents. Remove the groupBy(), or filter the documents of the relation after loading it.',
                class_basename($relation),
            ));
        }

        $query->addInternalStageAfterPaging(RelationLookup::ofEmbeddedDocuments($query, $relation, $alias, self::narrowingStages($relation, $embedded)));

        if ($selectionIsNarrowed) {
            $query->addInternalProjection($alias, 1);
        }

        $this->embeddedDocumentAliases[$alias] = true;

        return $alias;
    }

    /** @return list<array<string, mixed>> */
    private static function narrowingStages(EmbedsOneOrMany $relation, QueryBuilder $query): array
    {
        $filter = self::leadingFilter($query);

        return [
            ...($filter === [] ? [] : [['$match' => $filter]]),
            ['$replaceRoot' => ['newRoot' => '$' . $relation->getLocalKeyName()]],
            ...(empty($query->orders) ? [] : [['$sort' => self::sortFields($relation, $query->orders)]]),
            ...self::limitStages($query->limit),
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function limitStages(?int $limit): array
    {
        return match (true) {
            $limit === null => [],
            $limit === 0 => [['$match' => ['$expr' => false]]],
            default => [['$limit' => $limit]],
        };
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
            $sort[RelationLookup::resolveField($relation->getParent(), (string) $field, root: false)] = $direction;
        }

        return $sort;
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
        return self::filtersTheDocuments($query) || ! empty($query->orders) || $query->limit !== null;
    }

    private static function filtersTheDocuments(QueryBuilder $query): bool
    {
        return $query->wheres !== [];
    }

    /** @param list<Model> $models */
    private static function assertNotNestedEmbedding(array $models, EmbedsOneOrMany $relation): void
    {
        if (! self::isStoredInParentRoot($models[0])) {
            return;
        }

        throw new LogicException(sprintf(
            'Constraints on an eager loaded nested embedded relation are not supported, because the parents of %s are embedded themselves. Filter the nested documents with "whereHas()" or read them from the attribute.',
            class_basename($relation),
        ));
    }

    private static function isStoredInParentRoot(Model $model): bool
    {
        assert(method_exists($model, 'getParentRelation'));

        $parentRelation = $model->getParentRelation();

        return $parentRelation instanceof EmbedsOneOrMany && $parentRelation->getParent()->exists;
    }

    /**
     * @param list<Model> $models
     *
     * @return list<Model>
     */
    private function withNarrowedDocuments(array $models, string $name, ?string $alias): array
    {
        if ($alias === null) {
            return parent::eagerLoadRelation($models, $name, static fn () => null);
        }

        foreach ($models as $model) {
            $model->setRelation($name, $model->{$name}()->hydrate(self::narrowedDocumentsOf($model, $alias)));
        }

        return $models;
    }

    /**
     * @param list<Model> $models
     *
     * @return list<Model>
     */
    private function withReloadedDocuments(array $models, string $name, Closure $constraints): array
    {
        $reloading = $this->model->newModelQuery()->whereKey(collect($models)->map->getKey()->reject(static fn (mixed $key): bool => $key === null)->values()->all())->with([$name => $constraints]);
        assert($reloading instanceof self, 'Unexpected builder returned by the model, expecting ' . self::class);
        $reloaded = collect($reloading->getModels())->keyBy($this->model->getKeyName());
        $alias = $reloading->narrowedEmbeddedRelations[$name];

        foreach ($models as $model) {
            $reread = $reloaded->get($model->getKey());
            self::assertWasReadAgain($reread, $model, $name);

            $documents = $alias === null ? [] : self::narrowedDocumentsOf($reread, $alias);
            $model->setRelation($name, $model->{$name}()->hydrate($documents));
        }

        return $models;
    }

    private static function assertWasReadAgain(?Model $reread, Model $model, string $name): void
    {
        if ($reread !== null || ! $model->exists) {
            return;
        }

        throw new LogicException(sprintf(
            'The constrained relation "%s" cannot be loaded onto an existing model, because the model could not be read again by its key. Constrain the relation with "with()" when reading the models, or filter the documents after loading them.',
            $name,
        ));
    }

    /** @return list<array<string, mixed>> */
    private static function narrowedDocumentsOf(?Model $model, string $alias): array
    {
        return (array) ($model?->getAttributes()[$alias] ?? []);
    }
}
