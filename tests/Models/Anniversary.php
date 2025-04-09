<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\DocumentModel;
use MongoDB\Laravel\Eloquent\Model as Eloquent;
use MongoDB\Laravel\Query\Builder;

/**
 * @property string $name
 * @property string $anniversary
 * @mixin Eloquent
 * @method static Builder create(...$values)
 * @method static Builder truncate()
 * @method static Eloquent sole(...$parameters)
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
