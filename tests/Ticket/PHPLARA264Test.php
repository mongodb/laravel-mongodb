<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Ticket;

use InvalidArgumentException;
use MongoDB\Laravel\Tests\Models\Photo;
use MongoDB\Laravel\Tests\TestCase;

/** @see https://jira.mongodb.org/browse/PHPLARA-264 */
class PHPLARA264Test extends TestCase
{
    public function tearDown(): void
    {
        Photo::truncate();

        parent::tearDown();
    }

    public function testMorphToLazyLoadingRejectsNonModelMorphType(): void
    {
        $photo = Photo::create(['url' => 'http://example.com/poisoned.jpg']);
        $photo->has_image_type = PHPLARA264ArbitraryClass::class;
        $photo->has_image_id = '64a1b2c3d4e5f6a7b8c9d0e1';
        $photo->save();

        PHPLARA264ArbitraryClass::reset();

        try {
            $photo->hasImage;
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('does not resolve to an Eloquent model', $e->getMessage());
        }

        $this->assertFalse(PHPLARA264ArbitraryClass::wasInstantiated());
    }

    public function testMorphToEagerLoadingRejectsNonModelMorphType(): void
    {
        $photo = Photo::create(['url' => 'http://example.com/poisoned.jpg']);
        $photo->has_image_type = PHPLARA264ArbitraryClass::class;
        $photo->has_image_id = '64a1b2c3d4e5f6a7b8c9d0e1';
        $photo->save();

        PHPLARA264ArbitraryClass::reset();

        try {
            Photo::with('hasImage')->get();
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('does not resolve to an Eloquent model', $e->getMessage());
        }

        $this->assertFalse(PHPLARA264ArbitraryClass::wasInstantiated());
    }
}

class PHPLARA264ArbitraryClass
{
    private static bool $instantiated = false;

    public function __construct()
    {
        self::$instantiated = true;
    }

    public static function wasInstantiated(): bool
    {
        return self::$instantiated;
    }

    public static function reset(): void
    {
        self::$instantiated = false;
    }
}
