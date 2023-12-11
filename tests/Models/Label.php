<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
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

    public function users()
    {
        return $this->morphedByMany(User::class, 'labelled');
    }

    public function sqlUsers(): MorphToMany
    {
        return $this->morphedByMany(SqlUser::class, 'labeled');
    }

    public function clients()
    {
        return $this->morphedByMany(Client::class, 'labelled');
    }

    public function clientsWithCustomKeys()
    {
        return $this->morphedByMany(
            Client::class,
            'clabelled',
            'clabelleds',
            'clabel_ids',
            'cclabelled_id',
            'clabel_id',
            'cclient_id',
        );
    }
}
