<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class Experience extends Eloquent
{
    protected $connection       = 'mongodb';
    protected $collection       = 'experiences';
    protected static $unguarded = true;

    protected $casts = ['years' => 'int'];

    public function sqlUsers(): MorphToMany
    {
        return $this->morphToMany(SqlUser::class, 'experienced');
    }
}
