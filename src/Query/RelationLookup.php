<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Query;

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
use MongoDB\Laravel\Relations\EmbedsOneOrMany;

use function array_key_first;
use function class_basename;
use function is_array;
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

        if ($reason === null) {
            return;
        }

        throw new LogicException(sprintf(
            '%s is not supported for relationship lookups. %s',
            class_basename($relation),
            $reason,
        ));
    }

    /**
     * @param Relation<Model, Model, mixed> $relation
     *
     * @return array<string, mixed>
     */
    private static function joinExpression(Relation $relation): array
    {
        return match (true) {
            $relation instanceof BelongsTo => ['$eq' => [self::asString(self::field($relation->getOwnerKeyName())), ['$toString' => '$$local']]],
            $relation instanceof HasOneOrMany => ['$eq' => [self::asString(self::field($relation->getForeignKeyName())), ['$toString' => '$$local']]],
            $relation instanceof MorphToMany && $relation->getInverse() => [
                '$in' => [
                    self::asString(self::field($relation->getRelatedKeyName())),
                    self::pivotKeys($relation),
                ],
            ],
            $relation instanceof BelongsToMany => [
                '$in' => [
                    self::asString(self::field($relation->getRelatedKeyName())),
                    ['$map' => ['input' => ['$ifNull' => ['$$local', []]], 'in' => ['$toString' => '$$this']]],
                ],
            ],
            default => throw self::unsupported($relation),
        };
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
                        'input' => ['$ifNull' => ['$$local', []]],
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
     * @return string|array<string, mixed>
     */
    private static function localValue(Relation $relation, ?string $sourceAlias): string|array
    {
        $field = self::localField($relation);

        if ($sourceAlias === null) {
            return '$' . $field;
        }

        return ['$first' => '$' . $sourceAlias . '.' . $field];
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
        if ($constrainRelated === null) {
            return [];
        }

        $related = $relation->getRelated()->newQuery();
        $constrainRelated($related);

        $command = self::mongoQuery($related)->toMql();

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

        foreach (['sort' => '$sort', 'skip' => '$skip', 'limit' => '$limit', 'projection' => '$project'] as $option => $stage) {
            if (! isset($options[$option])) {
                continue;
            }

            $stages[] = [$stage => $options[$option]];
        }

        return $stages;
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
