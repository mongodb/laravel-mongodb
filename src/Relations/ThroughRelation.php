<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A through relation that resolves its intermediate keys with a query against
 * the through collection instead of a join.
 *
 * @internal
 */
interface ThroughRelation
{
    /**
     * The far parent key of every document matched by the given related query,
     * repeated once per document so that occurrences can be counted.
     *
     * @param Builder $relatedQuery
     *
     * @return Collection
     */
    public function pluckFarParentKeys(Builder $relatedQuery);

    /**
     * The key under which the related documents of the given far parent are
     * collected in the eager loading dictionary.
     *
     * @param Model $farParent
     *
     * @return string|null
     */
    public function getFarParentDictionaryKey(Model $farParent);

    /**
     * The local key on the far parent model.
     *
     * @return string
     */
    public function getLocalKeyName();
}
