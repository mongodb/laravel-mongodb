<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;

class Experience extends Eloquent
{
    protected $connection       = 'mongodb';
    protected $collection       = 'experiences';
    protected static $unguarded = true;

    protected $casts = ['years' => 'int'];

    public function skills()
    {
        return $this->belongsToMany(Skill::class, relatedKey: 'cskill_id');
    }

    public function skillsWithCustomParentKey()
    {
        return $this->belongsToMany(Skill::class, parentKey: 'cexperience_id');
    }
}
