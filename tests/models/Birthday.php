<?php

declare(strict_types=1);

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

/**
 * Class Birthday.
 *
 * @property string $name
 * @property string $birthday
 * @property string $day
 * @property string $month
 * @property string $year
 * @property string $time
 */
class Birthday extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'birthday';
    protected $fillable = ['name', 'birthday', 'day', 'month', 'year', 'time'];
}
