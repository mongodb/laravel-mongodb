<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;

/**
 * @property string $title
 * @property string $author
 * @property array $chapters
 */
class Label extends Eloquent
{
    protected $connection       = 'mongodb';
    protected $collection       = 'labels';
    protected static $unguarded = true;

    protected $fillable = [
        'name',
        'author',
        'chapters',
    ];

    /**
     * Get all the posts that are assigned this tag.
     */
    public function users()
    {
        return $this->morphedByMany(User::class, 'labelled');
    }

    /**
     * Get all the videos that are assigned this tag.
     */
    public function clients()
    {
        return $this->morphedByMany(Client::class, 'labelled');
    }
}
