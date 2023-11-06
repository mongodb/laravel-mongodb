<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class Photo extends Eloquent
{
    protected $connection       = 'mongodb';
    protected $collection       = 'photos';
    protected static $unguarded = true;

    public function hasImage(): MorphTo
    {
        return $this->morphTo();
    }

    public function hasImageWithCustomOwnerKey(): MorphTo
    {
        return $this->morphTo(ownerKey: 'cclient_id');
    }
}
