<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\DocumentModel;

class IdIsInt extends Model
{
    use DocumentModel;

    protected $primaryKey = '_id';
    protected $keyType = 'int';
    protected $connection = 'mongodb';
    protected static $unguarded = true;
    protected $casts = ['_id' => 'int'];
}
