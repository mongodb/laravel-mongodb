<?php

use Jenssegers\Mongodb\Model as Eloquent;
use Jenssegers\Mongodb\Eloquent\SoftDeletes;

class Soft extends Eloquent {

    use SoftDeletes;

    protected $collection = 'soft';
    protected static $unguarded = true;
    protected $dates = array('deleted_at');

}
