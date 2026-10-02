<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Relations;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough as EloquentHasManyThrough;
use Override;

/**
 * @template TRelatedModel of Model
 * @template TIntermediateModel of Model
 * @template TDeclaringModel of Model
 * @extends EloquentHasManyThrough<TRelatedModel, TIntermediateModel, TDeclaringModel>
 */
class HasManyThrough extends EloquentHasManyThrough implements ThroughRelation
{
    use ResolvesThroughKeys;

    /** @inheritdoc */
    #[Override]
    public function one()
    {
        return HasOneThrough::noConstraints(fn () => (new HasOneThrough(
            $this->getQuery(),
            $this->farParent,
            $this->throughParent,
            $this->getFirstKeyName(),
            $this->getForeignKeyName(),
            $this->getLocalKeyName(),
            $this->getSecondLocalKeyName(),
        ))->restoreThroughKeyState($this->throughKeys()));
    }

    /** @inheritdoc */
    #[Override]
    public function match(array $models, EloquentCollection $results, $relation)
    {
        return $this->matchAll($models, $results, $relation);
    }

    /**
     * Get the name of the "where in" method for eager loading.
     *
     * @inheritdoc
     */
    #[Override]
    protected function whereInMethod(Model $model, $key)
    {
        return 'whereIn';
    }
}
