<?php

namespace Jenssegers\Mongodb\Relations;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany as EloquentMorphMany;

class MorphMany extends EloquentMorphMany
{
    protected function buildDictionary(Collection $results)
    {
        $foreign = $this->getForeignKeyName();

        return $results->mapToDictionary(function ($result) use ($foreign) {
            return [(string) $result->{$foreign} => $result];
        })->all();
    }
}
