<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\DocumentModel;

/**
 * @property string $title
 * @property string $author
 * @property array $chapters
 */
class Book extends Model
{
    use DocumentModel;

    protected $primaryKey = 'title';
    protected $keyType = 'string';
    protected $connection = 'mongodb';
    protected string $collection = 'books';
    protected static $unguarded = true;

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function sqlAuthor(): BelongsTo
    {
        return $this->belongsTo(SqlUser::class, 'author_id');
    }
}
