<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use MongoDB\Laravel\Eloquent\Casts\ObjectId;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Parent of a polymorphic relation whose foreign key is stored as a native ObjectId.
 *
 * @property string $author_id
 */
class Post extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'posts';
    protected static $unguarded = true;

    protected $casts = ['author_id' => ObjectId::class];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
