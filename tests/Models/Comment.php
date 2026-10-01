<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use MongoDB\Laravel\Eloquent\Casts\ObjectId;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Child of a polymorphic relation whose foreign keys are stored as native ObjectIds.
 *
 * Neither "commentable_id" nor "author_id" ends with "_id" after a dot, so they are
 * only converted to ObjectId because of the cast declared on the model.
 *
 * @property string $commentable_id
 * @property string $commentable_type
 * @property string $author_id
 */
class Comment extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'comments';
    protected static $unguarded = true;

    protected $casts = [
        'commentable_id' => ObjectId::class,
        'author_id' => ObjectId::class,
    ];

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
