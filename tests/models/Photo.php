<?php

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class Photo extends Eloquent {

    protected $collection = 'photos';
    protected static $unguarded = true;

    public function imageable()
    {
        return $this->morphTo();
    }

}
