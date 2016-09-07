<?php

use Moloquent\Eloquent\Model as Eloquent;
use Moloquent\Eloquent\SoftDeletes;

class Soft extends Eloquent
{
    use SoftDeletes;

    protected $collection = 'soft';
    protected static $unguarded = true;
    protected $dates = ['deleted_at'];
}
