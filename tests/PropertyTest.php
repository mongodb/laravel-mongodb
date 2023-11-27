<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Eloquent;

use MongoDB\Laravel\Tests\Models\Animal;
use MongoDB\Laravel\Tests\Models\HiddenAnimal;
use MongoDB\Laravel\Tests\TestCase;

use function assert;

final class PropertyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Animal::truncate();
        HiddenAnimal::truncate();
    }

    public function testCanHideCertainProperties(): void
    {
        Animal::create([
            'name' => 'Lion',
            'country' => 'Central Africa',
            'can_be_eaten' => false,
        ]);
        HiddenAnimal::create([
            'name' => 'Sheep',
            'country' => 'Ireland',
            'can_be_eaten' => true,
        ]);

        $animal = Animal::sole();
        assert($animal instanceof Animal);
        self::assertSame('Central Africa', $animal->country);
        self::assertFalse($animal->can_be_eaten);

        self::assertArrayHasKey('name', $animal->toArray());
        self::assertArrayHasKey('country', $animal->toArray());
        self::assertArrayHasKey('can_be_eaten', $animal->toArray());

        $hiddenAnimal = HiddenAnimal::sole();
        assert($hiddenAnimal instanceof HiddenAnimal);
        self::assertSame('Ireland', $hiddenAnimal->country);
        self::assertTrue($hiddenAnimal->can_be_eaten);

        self::assertArrayHasKey('name', $hiddenAnimal->toArray());
        self::assertArrayNotHasKey('country', $hiddenAnimal->toArray(), 'the country column should be hidden');
        self::assertArrayHasKey('can_be_eaten', $hiddenAnimal->toArray());
    }
}
