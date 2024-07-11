<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use MongoDB\Laravel\Eloquent\DocumentModel;

class Experience extends Model
{
    use DocumentModel;

    protected $keyType = 'string';
    protected $connection = 'mongodb';
    protected string $collection = 'experiences';
    protected static $unguarded = true;

    protected $casts = ['years' => 'int'];

    public function sqlUsers(): MorphToMany
    {
        return $this->morphToMany(SqlUser::class, 'experienced');
    }
}
