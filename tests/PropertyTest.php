<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Eloquent;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use MongoDB\Laravel\Tests\Models\Anniversary;
use MongoDB\Laravel\Tests\Models\HiddenAnimal;
use MongoDB\Laravel\Tests\TestCase;

use function assert;

final class PropertyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        HiddenAnimal::truncate();
        Anniversary::truncate();
    }

    public function testCanHideCertainProperties(): void
    {
        HiddenAnimal::create([
            'name' => 'Sheep',
            'country' => 'Ireland',
            'can_be_eaten' => true,
        ]);

        $hiddenAnimal = HiddenAnimal::sole();
        assert($hiddenAnimal instanceof HiddenAnimal);
        self::assertSame('Ireland', $hiddenAnimal->country);
        self::assertTrue($hiddenAnimal->can_be_eaten);

        self::assertArrayHasKey('name', $hiddenAnimal->toArray());
        self::assertArrayNotHasKey('country', $hiddenAnimal->toArray(), 'the country column should be hidden');
        self::assertArrayHasKey('can_be_eaten', $hiddenAnimal->toArray());
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
