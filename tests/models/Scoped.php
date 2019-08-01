<?php

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;
use Jenssegers\Mongodb\Eloquent\Builder;

class Scoped extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'scoped';
    protected $fillable = ['name', 'favorite'];

    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('favorite', function (Builder $builder) {
            $builder->where('favorite', true);
        });
    }
}
