<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class Photo extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'photos';
    protected static $unguarded = true;

    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }
}
