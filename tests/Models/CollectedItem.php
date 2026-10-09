<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Attributes\CollectedBy;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\EmbedsMany;

#[CollectedBy(CollectedItemCollection::class)]
class CollectedItem extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'collected_items';
    protected static $unguarded = true;

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function embeddedChildren(): EmbedsMany
    {
        return $this->embedsMany(self::class);
    }
}
