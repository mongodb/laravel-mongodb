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
use MongoDB\Laravel\Query\Grammar;
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
    private const UNSUPPORTED_FILTER_OPERATORS = [
        '$text' => 'MongoDB requires a text search to be the first stage of the pipeline, before the join.',
        '$near' => 'MongoDB only accepts a proximity search in a find query, not in an aggregation $match.',
        '$nearSphere' => 'MongoDB only accepts a proximity search in a find query, not in an aggregation $match.',
    ];

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
                'from' => self::collectionName($relation),
                'let' => ['local' => self::localValue($relation)],
                'pipeline' => $pipeline,
                'as' => $alias,
            ],
        ]);
    }

    /** @param Relation<Model, Model, mixed> $relation */
    private static function collectionName(Relation $relation): string
    {
        $related = $relation->getRelated();

        return $related->getConnection()->getTablePrefix() . $related->getTable();
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

    /**
     * @param Relation<Model, Model, mixed> $relation
     *
     * @return array<string, mixed>
     */
    private static function joinExpression(Relation $relation): array
    {
        return [
            '$in' => [
                self::comparable('$' . self::relatedKeyName($relation)),
                self::localKeyValues($relation),
            ],
        ];
    }

    /** @param Relation<Model, Model, mixed> $relation */
    private static function relatedKeyName(Relation $relation): string
    {
        $name = match (true) {
            $relation instanceof BelongsTo => $relation->getOwnerKeyName(),
            $relation instanceof HasOneOrMany => $relation->getForeignKeyName(),
            $relation instanceof BelongsToMany => $relation->getRelatedKeyName(),
            default => throw self::unsupported($relation),
        };

        return self::resolveField($relation->getRelated(), $name);
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

        return ['$map' => ['input' => '$$local', 'in' => self::comparable('$$this')]];
    }

    /**
     * @param MorphToMany<Model, Model> $relation
     *
     * @return array<string, mixed>
     */
    private static function pivotKeys(MorphToMany $relation): array
    {
        $pivotKey = self::resolveField($relation->getRelated(), $relation->getRelatedPivotKeyName());

        return [
            '$map' => [
                'input' => [
                    '$filter' => [
                        'input' => '$$local',
                        'cond' => ['$eq' => ['$$this.' . $relation->getMorphType(), $relation->getMorphClass()]],
                    ],
                ],
                'in' => self::comparable('$$this.' . $pivotKey),
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
    private static function localValue(Relation $relation): array
    {
        $path = '$' . self::localField($relation);

        return self::withoutMissingKeys(self::holdsManyKeys($relation) ? ['$ifNull' => [$path, []]] : [$path]);
    }

    /**
     * @param array<string, mixed>|list<string> $keys
     *
     * @return array<string, mixed>
     */
    private static function withoutMissingKeys(array $keys): array
    {
        return ['$filter' => ['input' => $keys, 'cond' => ['$ne' => ['$$this', null]]]];
    }

    /** @param Relation<Model, Model, mixed> $relation */
    private static function holdsManyKeys(Relation $relation): bool
    {
        return $relation instanceof BelongsToMany;
    }

    /** @param Relation<Model, Model, mixed> $relation */
    private static function localField(Relation $relation): string
    {
        $name = match (true) {
            $relation instanceof BelongsTo => $relation->getForeignKeyName(),
            $relation instanceof HasOneOrMany => $relation->getLocalKeyName(),
            $relation instanceof MorphToMany && $relation->getInverse() => $relation->getTable(),
            $relation instanceof BelongsToMany => $relation->getRelatedPivotKeyName(),
            default => throw self::unsupported($relation),
        };

        return self::resolveField($relation->getParent(), $name);
    }

    /**
     * @param Relation<Model, Model, mixed>             $relation
     * @param Closure(EloquentBuilder<Model>):void|null $constrainRelated
     *
     * @return list<array<string, mixed>>
     */
    private static function relatedStages(Relation $relation, ?Closure $constrainRelated): array
    {
        $related = clone $relation->getQuery();

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
        $wheres = is_array($wheres) ? $wheres : [];
        $options = is_array($options) ? $options : [];

        self::assertFilterIsCompilable($wheres);
        self::assertOptionsAreCompilable($options);

        return self::stagesFor($wheres, $options);
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

    /** @param array<string, mixed> $options */
    private static function assertOptionsAreCompilable(array $options): void
    {
        if (! isset($options['collation'])) {
            return;
        }

        throw new LogicException('A "collation" option on a relationship constraint is not supported for relationship lookups. A lookup cannot set its own collation, so configure it on the outer aggregation instead.');
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

    /** @return array<string, mixed> */
    private static function comparable(string $expression): array
    {
        return [
            '$cond' => [
                ['$eq' => [['$type' => $expression], 'objectId']],
                ['$toString' => $expression],
                $expression,
            ],
        ];
    }

    private static function resolveField(Model $model, string $name): string
    {
        $grammar = $model->getConnection()->getQueryGrammar();

        if (! $grammar instanceof Grammar) {
            return self::field($name);
        }

        return (string) array_key_first($grammar->prepareFieldsForQuery([$name => true]));
    }

    public static function field(string $name): string
    {
        if ($name === 'id') {
            return '_id';
        }

        return $name;
    }
}
