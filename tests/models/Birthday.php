<?php

declare(strict_types=1);

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

/**
 * Class Birthday.
 *
 * @property string $name
 * @property string $birthday
 */
class Birthday extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'birthday';
    protected $fillable = ['name', 'birthday'];
}
