<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo as EloquentMorphTo;
use InvalidArgumentException;
use MongoDB\Laravel\Query\Builder as QueryBuilder;
use Override;

use function is_a;
use function sprintf;

/**
 * @template TRelatedModel of Model
 * @template TDeclaringModel of Model
 * @extends EloquentMorphTo<TRelatedModel, TDeclaringModel>
 */
class MorphTo extends EloquentMorphTo
{
    /** @inheritdoc */
    #[Override]
    public function addConstraints()
    {
        if (static::$constraints) {
            // For belongs to relationships, which are essentially the inverse of has one
            // or has many relationships, we need to actually query on the primary key
            // of the related models matching on the foreign key that's on a parent.
            $value = $this->getForeignKeyFrom($this->parent);
            QueryBuilder::assertKeyIsNotOperator($value);
            $this->query->where(
                $this->ownerKey ?? $this->getForeignKeyName(),
                '=',
                $value,
            );
        }
    }

    /** Get the name of the "where in" method for eager loading. */
    #[Override]
    protected function whereInMethod(Model $model, $key)
    {
        return 'whereIn';
    }

    /** @inheritdoc */
    #[Override]
    public function createModelByType($type)
    {
        self::assertMorphTypeIsEloquentModel(Model::getActualClassNameForMorph($type));

        return parent::createModelByType($type);
    }

    /**
     * @internal
     *
     * @throws InvalidArgumentException when the resolved morph type does not resolve to an Eloquent model.
     */
    public static function assertMorphTypeIsEloquentModel(string $class): void
    {
        if (is_a($class, Model::class, true)) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'The morph type "%s" does not resolve to an Eloquent model.',
            $class,
        ));
    }
}
