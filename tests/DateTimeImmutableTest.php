<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use MongoDB\Laravel\Tests\Models\Anniversary;

use function assert;

final class DateTimeImmutableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Anniversary::truncate();
    }

    protected function tearDown(): void
    {
        Date::useDefault();

        parent::tearDown();
    }

    public function testCanReturnCarbonImmutableObject(): void
    {
        Date::use(CarbonImmutable::class);

        Anniversary::create([
            'name' => 'John',
            'anniversary' => new CarbonImmutable('2020-01-01 00:00:00'),
        ]);

        $anniversary = Anniversary::sole();
        assert($anniversary instanceof Anniversary);
        self::assertInstanceOf(CarbonImmutable::class, $anniversary->anniversary);
    }
}
