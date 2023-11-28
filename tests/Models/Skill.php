<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class Skill extends Eloquent
{
    protected $connection       = 'mongodb';
    protected $collection       = 'skills';
    protected static $unguarded = true;

    public function sqlUsers(): MorphToMany
    {
        return $this->morphedByMany(SqlUser::class, 'skilled');
    }

    public function sqlBooks(): MorphToMany
    {
        return $this->morphedByMany(SqlBook::class, 'skilled');
    }
}
