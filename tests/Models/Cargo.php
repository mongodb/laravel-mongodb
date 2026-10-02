<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\DocumentModel;

class Cargo extends Model
{
    use DocumentModel;

    protected $keyType = 'string';
    protected $connection = 'mongodb';
    protected static $unguarded = true;
}
