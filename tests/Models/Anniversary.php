<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\DocumentModel;

/**
 * @property string $name
 * @property string $anniversary
 */
class Anniversary extends Model
{
    use DocumentModel;

    protected $keyType = 'string';
    protected $connection = 'mongodb';
    protected $table = 'anniversary';
    protected $fillable   = ['name', 'anniversary'];

    protected $casts = ['anniversary' => 'immutable_datetime'];
}
