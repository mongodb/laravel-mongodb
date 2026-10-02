<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use MongoDB\Laravel\Eloquent\DocumentModel;

class Studio extends Model
{
    use DocumentModel;

    protected $keyType = 'string';
    protected $connection = 'mongodb';
    protected $table = 'studios';
    protected static $unguarded = true;

    public function directors(): HasMany
    {
        return $this->hasMany(Director::class);
    }

    public function films(): HasManyThrough
    {
        return $this->hasManyThrough(Film::class, Director::class);
    }

    public function firstFilm(): HasOneThrough
    {
        return $this->hasOneThrough(Film::class, Director::class);
    }

    public function filmsWithCustomKeys(): HasManyThrough
    {
        return $this->hasManyThrough(
            Film::class,
            Director::class,
            'cstudio_ref',
            'cdirector_ref',
            'cstudio_id',
            'cdirector_id',
        );
    }

    public function firstFilmWithCustomKeys(): HasOneThrough
    {
        return $this->hasOneThrough(
            Film::class,
            Director::class,
            'cstudio_ref',
            'cdirector_ref',
            'cstudio_id',
            'cdirector_id',
        );
    }
}
