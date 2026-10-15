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
use MongoDB\Laravel\Connection;
use MongoDB\Laravel\Eloquent\Builder as MongoEloquentBuilder;
use MongoDB\Laravel\Eloquent\Model as DocumentModel;
use MongoDB\Laravel\Query\Builder;
use MongoDB\Laravel\Query\Grammar;
use MongoDB\Laravel\Query\Pipeline;
use MongoDB\Laravel\Relations\EmbedsOneOrMany;

use function array_key_first;
use function class_basename;
use function is_array;
use function sprintf;
use function str_ends_with;

/** @internal */
final class RelationLookup
{
    private const KEYS_VARIABLE = 'laravel_mongodb_relation_keys';

    private const UNSUPPORTED_OPTIONS = [
        'collation' => 'A lookup cannot set its own collation, so configure it on the outer aggregation instead.',
        'min' => 'An aggregation $match has no index bounds, so express the bound with "where()" instead.',
        'max' => 'An aggregation $match has no index bounds, so express the bound with "where()" instead.',
        'returnKey' => 'A lookup returns the related documents, not their index keys.',
        'let' => 'A lookup cannot define its own variables, so define them on the outer query instead.',
    ];

    private const UNSUPPORTED_FILTER_OPERATORS = [
        '$text' => 'MongoDB requires a text search to be the first stage of the pipeline, before the join.',
        '$near' => 'MongoDB only accepts a proximity search in a find query, not in an aggregation $match.',
        '$nearSphere' => 'MongoDB only accepts a proximity search in a find query, not in an aggregation $match.',
    ];

    /** @param list<array<string, mixed>> $stages */
    private function __construct(
        public readonly array $stages,
        public readonly string $keysField,
    ) {
    }

    /**
     * Supports the Filament bridge.
     *
     * @param Relation<Model, Model, mixed>        $relation
     * @param Closure(EloquentBuilder<Model>):void $constrainRelated
     * @param list<array<string, mixed>>           $finalStages
     */
    public static function for(
        Relation $relation,
        string $alias,
        ?Closure $constrainRelated = null,
        bool $aggregated = false,
        array $finalStages = [],
    ): self {
        self::assertLookupSupported($relation);
        self::assertServerSupportsLookup($relation);

        $relatedKey = self::relatedKeyName($relation);
        $keysField = $alias . '_keys';
        $related = self::relatedQuery($relation, $constrainRelated);

        $pipeline = [['$match' => ['$expr' => ['$gt' => [['$size' => '$$' . self::KEYS_VARIABLE], 0]]]]];

        foreach (self::morphTypeConstraints($relation) as $filter) {
            $pipeline[] = ['$match' => $filter];
        }

        $pipeline = [...$pipeline, ...self::relatedStages($related, $aggregated), ...$finalStages];

        return new self([
            ['$addFields' => [$keysField => self::presentKeys(self::localKeys($relation, $relatedKey))]],
            [
                '$lookup' => [
                    'from' => self::collectionName($relation, $related),
                    'localField' => $keysField,
                    'foreignField' => $relatedKey,
                    'let' => [self::KEYS_VARIABLE => '$' . $keysField],
                    'pipeline' => $pipeline,
                    'as' => $alias,
                ],
            ],
        ], $keysField);
    }

    /** @param Relation<Model, Model, mixed> $relation */
    private static function collectionName(Relation $relation, Builder $related): string
    {
        return $relation->getRelated()->getConnection()->getTablePrefix() . $related->from;
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
    private static function assertServerSupportsLookup(Relation $relation): void
    {
        $connection = $relation->getRelated()->getConnection();

        if (! $connection instanceof Connection || $connection->supportsRelationLookup()) {
            return;
        }

        throw new LogicException(sprintf(
            'Relationship lookups require MongoDB 5.0 or later, and the server of connection [%s] is older. Upgrade the server, or load the relation with "with()" or "withCount()" instead.',
            $connection->getName(),
        ));
    }

    /** @param Relation<Model, Model, mixed> $relation */
    private static function assertRelatedStoredInSameDatabase(Relation $relation): void
    {
        $related = $relation->getRelated();
        $parentConnection = $relation->getParent()->getConnection()->getName();
        $relatedConnection = $related->getConnection()->getName();

        if (DocumentModel::isDocumentModel($related) && $relatedConnection === $parentConnection) {
            return;
        }

        throw new LogicException(sprintf(
            'The hybrid relation to [%s] is not supported for relationship lookups. A lookup reads a collection of the database it runs in, so the related model must be stored in MongoDB on connection [%s], not on connection [%s].',
            $related::class,
            $parentConnection,
            $relatedConnection,
        ));
    }

    /** @param Relation<Model, Model, mixed> $relation */
    private static function relatedKeyName(Relation $relation): string
    {
        $name = match (true) {
            $relation instanceof BelongsTo => $relation->getOwnerKeyName(),
            $relation instanceof HasOneOrMany => $relation->getForeignKeyName(),
            $relation instanceof MorphToMany => $relation->getRelatedKeyName(),
            $relation instanceof BelongsToMany => $relation->getForeignPivotKeyName(),
            default => throw self::unsupported($relation),
        };

        return self::resolveField($relation->getRelated(), $name);
    }

    /**
     * @param Relation<Model, Model, mixed> $relation
     *
     * @return array<array-key, mixed>
     */
    private static function localKeys(Relation $relation, string $relatedKey): array
    {
        $localField = self::localField($relation);
        $path = '$' . $localField;

        if ($relation instanceof MorphToMany && $relation->getInverse()) {
            return self::pivotKeys($relation, $path, $relatedKey);
        }

        if ($relation instanceof MorphToMany) {
            return ['$map' => ['input' => ['$ifNull' => [$path, []]], 'in' => self::castedFor($relatedKey, '$$this')]];
        }

        if ($localField !== '_id') {
            return [self::castedFor($relatedKey, $path)];
        }

        return [self::castedFor($relatedKey, self::documentIdAsString($path))];
    }

    /**
     * @param array<array-key, mixed> $keys
     *
     * @return array<string, mixed>
     */
    private static function presentKeys(array $keys): array
    {
        return ['$filter' => ['input' => $keys, 'cond' => ['$ne' => ['$$this', null]]]];
    }

    /**
     * @param MorphToMany<Model, Model> $relation
     *
     * @return array<string, mixed>
     */
    private static function pivotKeys(MorphToMany $relation, string $path, string $relatedKey): array
    {
        $pivotKey = self::resolveField($relation->getRelated(), $relation->getRelatedPivotKeyName());

        return [
            '$map' => [
                'input' => [
                    '$filter' => [
                        'input' => ['$ifNull' => [$path, []]],
                        'cond' => ['$eq' => ['$$this.' . $relation->getMorphType(), $relation->getMorphClass()]],
                    ],
                ],
                'in' => self::castedFor($relatedKey, '$$this.' . $pivotKey),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function documentIdAsString(string $path): array
    {
        return ['$cond' => [['$eq' => [['$type' => $path], 'objectId']], ['$toString' => $path], $path]];
    }

    /**
     * @param array<string, mixed>|string $key
     *
     * @return array<string, mixed>|string
     */
    private static function castedFor(string $relatedKey, array|string $key): array|string
    {
        if ($relatedKey !== '_id' && ! str_ends_with($relatedKey, '._id')) {
            return $key;
        }

        return ['$convert' => ['input' => $key, 'to' => 'objectId', 'onError' => $key, 'onNull' => $key]];
    }

    /**
     * @param Relation<Model, Model, mixed> $relation
     *
     * @return list<array<string, mixed>>
     */
    private static function morphTypeConstraints(Relation $relation): array
    {
        return match (true) {
            $relation instanceof MorphOneOrMany => [
                [self::resolveField($relation->getRelated(), $relation->getMorphType()) => $relation->getMorphClass()],
            ],
            $relation instanceof MorphToMany && ! $relation->getInverse() => [
                [self::resolveField($relation->getRelated(), $relation->getTable() . '.' . $relation->getMorphType()) => $relation->getMorphClass()],
            ],
            default => [],
        };
    }

    /** @param Relation<Model, Model, mixed> $relation */
    private static function localField(Relation $relation): string
    {
        $name = match (true) {
            $relation instanceof BelongsTo => $relation->getForeignKeyName(),
            $relation instanceof HasOneOrMany => $relation->getLocalKeyName(),
            $relation instanceof MorphToMany && $relation->getInverse() => $relation->getTable(),
            $relation instanceof MorphToMany => $relation->getRelatedPivotKeyName(),
            $relation instanceof BelongsToMany => $relation->getParentKeyName(),
            default => throw self::unsupported($relation),
        };

        return self::resolveField($relation->getParent(), $name);
    }

    /**
     * @param Relation<Model, Model, mixed>             $relation
     * @param Closure(EloquentBuilder<Model>):void|null $constrainRelated
     */
    private static function relatedQuery(Relation $relation, ?Closure $constrainRelated): Builder
    {
        $related = clone $relation->getQuery();

        if ($constrainRelated !== null) {
            self::applyConstraints($related, $constrainRelated);
        }

        return self::mongoQuery($related->applyScopes());
    }

    /** @return list<array<string, mixed>> */
    private static function relatedStages(Builder $related, bool $aggregated): array
    {
        $command = $related->toMql();

        if (! isset($command['find'])) {
            throw new LogicException(sprintf(
                'Relationship constraints producing a "%s" command are not supported for relationship lookups.',
                (string) array_key_first($command),
            ));
        }

        [$wheres, $options] = $command['find'] + [[], []];
        $wheres = is_array($wheres) ? $wheres : [];
        $options = is_array($options) ? $options : [];
        if ($aggregated) {
            $options = self::optionsForAggregate($options);
        }

        self::assertFilterIsCompilable($wheres);
        self::assertOptionsAreCompilable($options);

        return self::stagesFor($wheres, $options);
    }

    /**
     * @param EloquentBuilder<Model>               $related
     * @param Closure(EloquentBuilder<Model>):void $constrainRelated
     */
    private static function applyConstraints(EloquentBuilder $related, Closure $constrainRelated): void
    {
        if ($related instanceof MongoEloquentBuilder) {
            $related->applyGroupedConstraints($constrainRelated);

            return;
        }

        $constrainRelated($related);
    }

    /** @param array<array-key, mixed> $filter */
    private static function assertFilterIsCompilable(array $filter): void
    {
        foreach (self::UNSUPPORTED_FILTER_OPERATORS as $operator => $reason) {
            if (! self::containsOperator($filter, $operator)) {
                continue;
            }

            throw new LogicException(sprintf(
                'A "%s" constraint is not supported for relationship lookups. %s Query the related collection directly instead.',
                $operator,
                $reason,
            ));
        }
    }

    /** @param array<array-key, mixed> $filter */
    private static function containsOperator(array $filter, string $operator): bool
    {
        foreach ($filter as $key => $value) {
            if ($key === $operator) {
                return true;
            }

            if (is_array($value) && self::containsOperator($value, $operator)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private static function optionsForAggregate(array $options): array
    {
        if (isset($options['skip'])) {
            throw new LogicException('An "offset()" on a relationship aggregate constraint is not supported. The aggregate covers every related document, so filter them with "where()" instead.');
        }

        unset($options['projection'], $options['sort'], $options['limit']);

        return $options;
    }

    /** @param array<string, mixed> $options */
    private static function assertOptionsAreCompilable(array $options): void
    {
        foreach (self::UNSUPPORTED_OPTIONS as $option => $reason) {
            if (! isset($options[$option])) {
                continue;
            }

            throw new LogicException(sprintf(
                'A "%s" option on a relationship constraint is not supported for relationship lookups. %s',
                $option,
                $reason,
            ));
        }
    }

    /**
     * @param array<string, mixed> $wheres
     * @param array<string, mixed> $options
     *
     * @return list<array<string, mixed>>
     */
    private static function stagesFor(array $wheres, array $options): array
    {
        $match = $wheres === [] ? [] : [['$match' => $wheres]];

        return [...$match, ...Pipeline::optionStages($options)];
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

    /**
     * Supports the Filament bridge.
     *
     * @internal
     */
    public static function resolveField(Model $model, string $name): string
    {
        $grammar = $model->getConnection()->getQueryGrammar();

        if (! $grammar instanceof Grammar) {
            return $name === 'id' ? '_id' : $name;
        }

        return (string) array_key_first($grammar->prepareFieldsForQuery([$name => true]));
    }
}
