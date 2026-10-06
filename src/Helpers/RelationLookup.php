<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Helpers;

use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use LogicException;
use MongoDB\Laravel\Eloquent\Model as DocumentModel;
use MongoDB\Laravel\Query\Builder;
use MongoDB\Laravel\Relations\EmbedsOneOrMany;

use function array_is_list;
use function array_key_first;
use function class_basename;
use function count;
use function is_array;
use function is_int;
use function sprintf;

/** @internal */
final class RelationLookup
{
    /** @param array<string, mixed> $stage */
    private function __construct(
        public readonly string $alias,
        public readonly array $stage,
    ) {
    }

    /**
     * @param Relation<Model, Model, mixed>        $relation
     * @param Closure(EloquentBuilder<Model>):void $constrainRelated
     */
    public static function for(
        Relation $relation,
        string $alias,
        ?string $sourceAlias,
        ?Closure $constrainRelated = null,
    ): self {
        self::assertLookupSupported($relation);

        $pipeline = [['$match' => ['$expr' => self::joinExpression($relation)]]];

        foreach (self::morphTypeConstraints($relation) as $filter) {
            $pipeline[] = ['$match' => $filter];
        }

        $pipeline = [...$pipeline, ...self::relatedStages($relation, $constrainRelated)];

        return new self($alias, [
            '$lookup' => [
                'from' => $relation->getRelated()->getTable(),
                'let' => ['local' => self::localValue($relation, $sourceAlias)],
                'pipeline' => $pipeline,
                'as' => $alias,
            ],
        ]);
    }

    /** @param Relation<Model, Model, mixed> $relation */
    private static function assertLookupSupported(Relation $relation): void
    {
        $reason = match (true) {
            $relation instanceof MorphTo => 'The related collection differs per document, which one lookup cannot express. Eager load the relation with "with()", or query each morph type on its own.',
            $relation instanceof EmbedsOneOrMany => 'The related documents are already part of the parent document. Read them from the attribute, or match them on their dotted path.',
            default => null,
        };

        if ($reason !== null) {
            throw new LogicException(sprintf(
                '%s is not supported for relationship lookups. %s',
                class_basename($relation),
                $reason,
            ));
        }

        self::assertRelatedStoredInSameDatabase($relation);
    }

    /** @param Relation<Model, Model, mixed> $relation */
    private static function assertRelatedStoredInSameDatabase(Relation $relation): void
    {
        $related = $relation->getRelated();
        $connection = $relation->getParent()->getConnectionName();

        if (DocumentModel::isDocumentModel($related) && $related->getConnectionName() === $connection) {
            return;
        }

        throw new LogicException(sprintf(
            'The hybrid relation to [%s] is not supported for relationship lookups. A lookup reads a collection of the database it runs in, so the related model must be stored in MongoDB on connection [%s], not on connection [%s].',
            $related::class,
            (string) $connection,
            (string) $related->getConnectionName(),
        ));
    }

    /**
     * @param Relation<Model, Model, mixed> $relation
     *
     * @return array<string, mixed>
     */
    private static function joinExpression(Relation $relation): array
    {
        return [
            '$in' => [
                self::asString(self::field(self::relatedKeyName($relation))),
                self::localKeyValues($relation),
            ],
        ];
    }

    /** @param Relation<Model, Model, mixed> $relation */
    private static function relatedKeyName(Relation $relation): string
    {
        return match (true) {
            $relation instanceof BelongsTo => $relation->getOwnerKeyName(),
            $relation instanceof HasOneOrMany => $relation->getForeignKeyName(),
            $relation instanceof BelongsToMany => $relation->getRelatedKeyName(),
            default => throw self::unsupported($relation),
        };
    }

    /**
     * @param Relation<Model, Model, mixed> $relation
     *
     * @return array<string, mixed>
     */
    private static function localKeyValues(Relation $relation): array
    {
        if ($relation instanceof MorphToMany && $relation->getInverse()) {
            return self::pivotKeys($relation);
        }

        return ['$map' => ['input' => '$$local', 'in' => ['$toString' => '$$this']]];
    }

    /**
     * @param MorphToMany<Model, Model> $relation
     *
     * @return array<string, mixed>
     */
    private static function pivotKeys(MorphToMany $relation): array
    {
        return [
            '$map' => [
                'input' => [
                    '$filter' => [
                        'input' => '$$local',
                        'cond' => ['$eq' => ['$$this.' . $relation->getMorphType(), $relation->getMorphClass()]],
                    ],
                ],
                'in' => ['$toString' => '$$this.' . $relation->getRelatedPivotKeyName()],
            ],
        ];
    }

    /**
     * @param Relation<Model, Model, mixed> $relation
     *
     * @return list<array<string, mixed>>
     */
    private static function morphTypeConstraints(Relation $relation): array
    {
        return match (true) {
            $relation instanceof MorphOneOrMany => [[$relation->getMorphType() => $relation->getMorphClass()]],
            $relation instanceof MorphToMany && ! $relation->getInverse() => [
                [$relation->getTable() . '.' . $relation->getMorphType() => $relation->getMorphClass()],
            ],
            default => [],
        };
    }

    /**
     * @param Relation<Model, Model, mixed> $relation
     *
     * @return array<string, mixed>
     */
    private static function localValue(Relation $relation, ?string $sourceAlias): array
    {
        $path = '$' . self::localField($relation);

        if ($sourceAlias === null) {
            return self::withoutMissingKeys(self::holdsManyKeys($relation) ? ['$ifNull' => [$path, []]] : [$path]);
        }

        $collected = '$' . $sourceAlias . '.' . self::localField($relation);

        return self::withoutMissingKeys(
            self::holdsManyKeys($relation) ? self::flatten($collected) : $collected,
        );
    }

    /**
     * @param string|array<string, mixed>|list<string> $keys
     *
     * @return array<string, mixed>
     */
    private static function withoutMissingKeys(string|array $keys): array
    {
        return ['$filter' => ['input' => $keys, 'cond' => ['$ne' => ['$$this', null]]]];
    }

    /** @return array<string, mixed> */
    private static function flatten(string $path): array
    {
        return [
            '$reduce' => [
                'input' => ['$ifNull' => [$path, []]],
                'initialValue' => [],
                'in' => ['$concatArrays' => ['$$value', ['$ifNull' => ['$$this', []]]]],
            ],
        ];
    }

    /** @param Relation<Model, Model, mixed> $relation */
    private static function holdsManyKeys(Relation $relation): bool
    {
        return $relation instanceof BelongsToMany;
    }

    /** @param Relation<Model, Model, mixed> $relation */
    private static function localField(Relation $relation): string
    {
        return match (true) {
            $relation instanceof BelongsTo => self::field($relation->getForeignKeyName()),
            $relation instanceof HasOneOrMany => self::field($relation->getLocalKeyName()),
            $relation instanceof MorphToMany && $relation->getInverse() => $relation->getTable(),
            $relation instanceof BelongsToMany => self::field($relation->getRelatedPivotKeyName()),
            default => throw self::unsupported($relation),
        };
    }

    /**
     * @param Relation<Model, Model, mixed>             $relation
     * @param Closure(EloquentBuilder<Model>):void|null $constrainRelated
     *
     * @return list<array<string, mixed>>
     */
    private static function relatedStages(Relation $relation, ?Closure $constrainRelated): array
    {
        $related = $relation->getRelated()->newQuery()
            ->withoutGlobalScopes($relation->getQuery()->removedScopes());

        if ($constrainRelated !== null) {
            $constrainRelated($related);
        }

        $command = self::mongoQuery($related->applyScopes())->toMql();

        if (! isset($command['find'])) {
            throw new LogicException(sprintf(
                'Relationship constraints producing a "%s" command are not supported for relationship lookups.',
                (string) array_key_first($command),
            ));
        }

        [$wheres, $options] = $command['find'] + [[], []];

        return self::stagesFor(
            is_array($wheres) ? $wheres : [],
            is_array($options) ? $options : [],
        );
    }

    /**
     * @param array<string, mixed> $wheres
     * @param array<string, mixed> $options
     *
     * @return list<array<string, mixed>>
     */
    private static function stagesFor(array $wheres, array $options): array
    {
        $stages = [];

        if ($wheres !== []) {
            $stages[] = ['$match' => $wheres];
        }

        foreach (['sort' => '$sort', 'skip' => '$skip', 'limit' => '$limit'] as $option => $stage) {
            if (! isset($options[$option])) {
                continue;
            }

            $stages[] = [$stage => $options[$option]];
        }

        if (isset($options['projection']) && is_array($options['projection'])) {
            $stages[] = ['$project' => self::projection($options['projection'])];
        }

        return $stages;
    }

    /**
     * @param array<string, mixed> $projection
     *
     * @return array<string, mixed>
     */
    private static function projection(array $projection): array
    {
        $projected = [];

        foreach ($projection as $field => $specification) {
            $projected[$field] = self::projectedField($field, $specification);
        }

        return $projected;
    }

    private static function projectedField(string $field, mixed $specification): mixed
    {
        if (! is_array($specification)) {
            return $specification;
        }

        if (isset($specification['$slice'])) {
            return ['$slice' => self::sliceArguments($field, $specification['$slice'])];
        }

        throw new LogicException(sprintf(
            'The projection of "%s" uses the operator "%s", which a relationship lookup cannot compile into an aggregation stage. Project the field without it, or read the relation without a lookup.',
            $field,
            (string) array_key_first($specification),
        ));
    }

    /** @return list<mixed> */
    private static function sliceArguments(string $field, mixed $arguments): array
    {
        if (is_int($arguments)) {
            return ['$' . $field, $arguments];
        }

        if (is_array($arguments) && array_is_list($arguments) && count($arguments) === 2) {
            return ['$' . $field, $arguments[0], $arguments[1]];
        }

        throw new LogicException(sprintf(
            'The projection of "%s" slices with arguments a relationship lookup cannot compile into an aggregation stage. Slice with a count, or with a skip and a count.',
            $field,
        ));
    }

    /** @param EloquentBuilder<Model> $related */
    private static function mongoQuery(EloquentBuilder $related): Builder
    {
        $query = $related->getQuery();

        if ($query instanceof Builder) {
            return $query;
        }

        throw new LogicException('Relationship constraints can only be compiled for MongoDB queries.');
    }

    /** @param Relation<Model, Model, mixed> $relation */
    private static function unsupported(Relation $relation): LogicException
    {
        return new LogicException(sprintf(
            '%s is not supported for relationship lookups. One lookup covers a single relationship hop; chain a lookup per hop, or eager load the relation with "with()".',
            class_basename($relation),
        ));
    }

    /** @return array<string, string> */
    private static function asString(string $field): array
    {
        return ['$toString' => '$' . $field];
    }

    public static function field(string $name): string
    {
        if ($name === 'id') {
            return '_id';
        }

        return $name;
    }
}
