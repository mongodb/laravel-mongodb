<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\DocumentModel;
use MongoDB\Laravel\Eloquent\Model as Eloquent;
use MongoDB\Laravel\Query\Builder;

/**
 * @property string $name
 * @property string $country
 * @property bool $can_be_eaten
 * @mixin Eloquent
 * @method static Builder create(...$values)
 * @method static Builder truncate()
 * @method static Eloquent sole(...$parameters)
 */
final class HiddenAnimal extends Model
{
    use DocumentModel;

    protected $primaryKey = '_id';
    protected $keyType = 'string';
    protected $fillable = [
        'name',
        'country',
        'can_be_eaten',
    ];

    protected $hidden = ['country'];
}
