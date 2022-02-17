<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

/**
 * Class Book.
 *
 * @property string $title
 * @property string $author
 * @property array $chapters
 */
class Book extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'books';
    protected static $unguarded = true;
    protected $primaryKey = 'title';

    public function author(): BelongsTo
    {
        return $this->belongsTo('User', 'author_id');
    }

    public function mysqlAuthor(): BelongsTo
    {
        return $this->belongsTo('MysqlUser', 'author_id');
    }
}
