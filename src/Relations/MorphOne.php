<?php

namespace Moloquent\Relations;

use Illuminate\Database\Eloquent\Relations\MorphOne as EloquentMorphOne;

class MorphOne extends EloquentMorphOne
{
    use HasOneOrManyTrait;
}
