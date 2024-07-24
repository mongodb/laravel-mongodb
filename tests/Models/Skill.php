<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use MongoDB\Laravel\Eloquent\DocumentModel;

class Skill extends Model
{
    use DocumentModel;

    protected $primaryKey = '_id';
    protected $keyType = 'string';
    protected $connection = 'mongodb';
    protected $table = 'skills';
    protected static $unguarded = true;

    public function sqlUsers(): BelongsToMany
    {
        return $this->belongsToMany(SqlUser::class);
    }
}
