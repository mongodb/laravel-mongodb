<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Ticket;

use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Tests\TestCase;

/**
 * @see https://github.com/mongodb/laravel-mongodb/issues/3326
 * @see https://jira.mongodb.org/browse/PHPORM-309
 */
class GH3326Test extends TestCase
{
    public function testCreatedEventCanSafelyCallSave(): void
    {
        $model = new GH3326Model();
        $model->foo = 'bar';
        $model->save();

        $fresh = $model->fresh();

        $this->assertEquals('bar', $fresh->foo);
        $this->assertEquals('written-in-created', $fresh->extra);
    }
}

class GH3326Model extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'test_gh3326';
    protected $guarded = [];

    protected static function booted(): void
    {
        static::created(function ($model) {
            $model->extra = 'written-in-created';
            $model->saveQuietly();
        });
    }
}
