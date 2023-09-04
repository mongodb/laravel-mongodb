<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use MongoDB\Laravel\Eloquent\Builder;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class Scoped extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'scoped';
    protected $fillable   = ['name', 'favorite'];

    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('favorite', function (Builder $builder) {
            $builder->where('favorite', true);
        });
    }
}
