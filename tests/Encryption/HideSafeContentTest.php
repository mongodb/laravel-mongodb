<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Encryption;

use MongoDB\Laravel\Encryption\AutoEncryption;
use MongoDB\Laravel\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class HideSafeContentTest extends TestCase
{
    #[DataProvider('provideProjectionWithoutInclusion')]
    public function testExclusionIsAddedWhenNothingIsIncluded(array $projection): void
    {
        $this->assertSame(
            $projection + ['__safeContent__' => 0],
            AutoEncryption::hideSafeContent($projection),
        );
    }

    public static function provideProjectionWithoutInclusion(): iterable
    {
        yield 'empty' => [[]];
        yield 'exclusion' => [['name' => 0]];
        yield 'several exclusions' => [['name' => 0, 'age' => 0]];
        yield 'excluded id' => [['_id' => 0]];
        yield 'dotted exclusion' => [['billing.credit_card_number' => 0]];
        yield 'false exclusion' => [['name' => false]];
        yield 'slice' => [['list' => ['$slice' => 2]]];
        yield 'meta' => [['score' => ['$meta' => 'textScore']]];
        yield 'elemMatch' => [['list' => ['$elemMatch' => ['$gt' => 1]]]];
        yield 'slice and exclusion' => [['list' => ['$slice' => 2], 'name' => 0]];
        yield 'every projection operator' => [
            [
                'list' => ['$slice' => 2],
                'score' => ['$meta' => 'textScore'],
                'items' => ['$elemMatch' => ['$gt' => 1]],
            ],
        ];
    }

    #[DataProvider('provideProjectionWithInclusion')]
    public function testProjectionIsUntouchedWhenFieldsAreIncluded(array $projection): void
    {
        $this->assertSame($projection, AutoEncryption::hideSafeContent($projection));
    }

    public static function provideProjectionWithInclusion(): iterable
    {
        yield 'inclusion' => [['name' => 1]];
        yield 'true inclusion' => [['name' => true]];
        yield 'included id' => [['_id' => 1]];
        yield 'truthy inclusion' => [['name' => 2]];
        yield 'negative inclusion' => [['name' => -1]];
        yield 'string inclusion' => [['name' => 'yes']];
        yield 'dotted inclusion' => [['billing.amount' => 1]];
        yield 'expression' => [['total' => ['$literal' => 5]]];
        yield 'switch expression' => [['label' => ['$switch' => ['branches' => []]]]];
        yield 'concat expression' => [['label' => ['$concat' => ['$name', '!']]]];
        yield 'positional' => [['list.$' => 1]];
        yield 'positional beside exclusion' => [['list.$' => 1, 'name' => 0]];
        yield 'slice beside inclusion' => [['list' => ['$slice' => 2], 'name' => 1]];
    }

    public function testExclusionIsNotAddedTwice(): void
    {
        $projection = ['name' => 0, '__safeContent__' => 0];

        $this->assertSame($projection, AutoEncryption::hideSafeContent($projection));
    }
}
