<?php
namespace Jenssegers\Mongodb\Relations;

use Illuminate\Database\Eloquent\Relations\MorphMany as EloquentMorphMany;

class MorphMany extends EloquentMorphMany
{
    use HasOneOrManyTrait;
}
