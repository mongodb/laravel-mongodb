<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use MongoDB\Laravel\Eloquent\DocumentModel;

class Photo extends Model
{
    use DocumentModel;

    protected $primaryKey = '_id';
    protected $keyType = 'string';
    protected $connection = 'mongodb';
    protected string $collection = 'photos';
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
