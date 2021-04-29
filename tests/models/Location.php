<?php

declare(strict_types=1);

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class Location extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'locations';
    protected static $unguarded = true;
}
