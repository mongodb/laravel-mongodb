<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\DocumentModel;

/**
 * @property string $name
 * @property string $country
 * @property bool $can_be_eaten
 * @property string $secret
 */
final class HiddenAnimal extends Model
{
    use DocumentModel;

    protected $keyType = 'string';
    protected $fillable = [
        'name',
        'country',
        'can_be_eaten',
        'secret',
    ];

    protected $hidden = ['country', 'secret'];

    /**
     * Reproduces laravel/passport Client::secret(): a set-only Attribute mutator
     * whose method name collides with a hidden attribute.
     */
    protected function secret(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => $value,
        );
    }
}
