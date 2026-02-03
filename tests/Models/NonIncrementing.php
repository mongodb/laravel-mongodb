<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\DocumentModel;

/**
 * @property string $id
 * @property string $name
 */
class NonIncrementing extends Model
{
    use DocumentModel;

    protected $keyType = 'string';
    protected $connection = 'mongodb';

    protected $fillable = ['name'];
    protected static $unguarded = true;
    public $incrementing = false;
}
