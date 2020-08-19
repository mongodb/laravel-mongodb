<?php
declare(strict_types=1);

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class Guarded extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'guarded';
    protected $guarded = ['foobar'];
}
