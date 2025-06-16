<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\DocumentModel;

/**
 * @property string $name
 * @property string $country
 * @property bool $can_be_eaten
 */
final class HiddenAnimal extends Model
{
    use DocumentModel;

    protected $keyType = 'string';
    protected $fillable = [
        'name',
        'country',
        'can_be_eaten',
    ];

    protected $hidden = ['country'];
}
