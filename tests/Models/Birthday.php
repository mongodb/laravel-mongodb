<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\DocumentModel;

/**
 * @property string $name
 * @property string $birthday
 * @property string $time
 */
class Birthday extends Model
{
    use DocumentModel;

    protected $keyType = 'string';
    protected $connection = 'mongodb';
    protected string $collection = 'birthday';
    protected $fillable   = ['name', 'birthday'];

    protected $casts = ['birthday' => 'datetime'];
}
