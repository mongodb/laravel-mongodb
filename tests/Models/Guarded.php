<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\DocumentModel;

class Guarded extends Model
{
    use DocumentModel;

    protected $primaryKey = '_id';
    protected $keyType = 'string';
    protected $connection = 'mongodb';
    protected string $collection = 'guarded';
    protected $guarded = ['foobar', 'level1->level2'];
}
