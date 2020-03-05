<?php

declare(strict_types=1);

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class Address extends Eloquent
{
    protected $connection = 'mongodb';
    protected static $unguarded = true;
}
