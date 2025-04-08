<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Ticket;

use MongoDB\Laravel\Tests\Models\Location;
use MongoDB\Laravel\Tests\TestCase;

/** @see https://github.com/mongodb/laravel-mongodb/discussions/3335 */
class GH3335Test extends TestCase
{
    public function tearDown(): void
    {
        Location::truncate();

        parent::tearDown();
    }

    public function testNumericalFieldName()
    {
        $model = new Location();
        $model->id = 'foo';
        $model->save();

        $model = Location::find('foo');
        $model->{'38'} = 'PHP';
        $model->save();

        $model = Location::find('foo');
        self::assertSame('PHP', $model->{'38'});
    }
}
