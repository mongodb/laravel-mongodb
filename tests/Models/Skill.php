<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use MongoDB\BSON\ObjectId;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class Skill extends Eloquent
{
    protected $connection       = 'mongodb';
    protected $collection       = 'skills';
    protected static $unguarded = true;
    protected $primaryKey = 'custom_id';

    public function getForeignKey()
    {
        return 'custom_skill_id';
    }

    protected static function booted()
    {
        static::creating(fn (self $model) => $model->custom_id = (string)(new ObjectId()));
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
