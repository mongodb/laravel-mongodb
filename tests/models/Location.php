<?php

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class Location extends Eloquent
{
    protected $collection = 'locations';
    protected static $unguarded = true;
}
