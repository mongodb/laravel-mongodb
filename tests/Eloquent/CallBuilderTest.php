<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Eloquent;

use BadMethodCallException;
use Generator;
use MongoDB\Laravel\Eloquent\Builder;
use MongoDB\Laravel\Query\Builder as QueryBuilder;
use MongoDB\Laravel\Tests\Models\User;
use MongoDB\Laravel\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

use function assert;

final class CallBuilderTest extends TestCase
{
    protected function tearDown(): void
    {
        User::truncate();
    }

    #[Dataprovider('provideFunctionNames')]
    public function testCallingABuilderMethodDoesNotReturnTheBuilderInstance(string $method, $parameters = []): void
    {
        $builder = User::query()->newQuery();
        assert($builder instanceof Builder);

        self::assertNotInstanceOf(Builder::class, $builder->{$method}(...$parameters));
    }

    public static function provideFunctionNames(): Generator
    {
        yield 'does not exist' => ['doesntExist'];
        yield 'get bindings' => ['getBindings'];
        yield 'get connection' => ['getConnection'];
        yield 'get grammar' => ['getGrammar'];
        yield 'insert get id' => ['insertGetId', [['user' => 'foo']]];
        yield 'to Mql' => ['toMql'];
        yield 'to Sql' => ['toSql'];
        yield 'to Raw Sql' => ['toRawSql'];
        yield 'average' => ['average', ['name']];
        yield 'avg' => ['avg', ['name']];
        yield 'count' => ['count', ['name']];
        yield 'exists' => ['exists'];
        yield 'insert' => ['insert', [['name']]];
        yield 'max' => ['max', ['name']];
        yield 'min' => ['min', ['name']];
        yield 'pluck' => ['pluck', ['name']];
        yield 'pull' => ['pull', ['name']];
        yield 'push' => ['push', ['name']];
        yield 'raw' => ['raw'];
        yield 'sum' => ['sum', ['name']];
    }

    #[Test]
    #[DataProvider('provideUnsupportedMethods')]
    public function callingUnsupportedMethodThrowsAnException(string $method, string $exceptionClass, string $exceptionMessage, $parameters = []): void
    {
        $builder = User::query()->newQuery();
        assert($builder instanceof Builder);

        $this->expectException($exceptionClass);
        $this->expectExceptionMessage($exceptionMessage);

        $builder->{$method}(...$parameters);
    }

    public static function provideUnsupportedMethods(): Generator
    {
        yield 'insert or ignore' => [
            'insertOrIgnore',
            RuntimeException::class,
            'This database engine does not support inserting while ignoring errors',
            [['name' => 'Jane']],
        ];

        yield 'insert using' => [
            'insertUsing',
            BadMethodCallException::class,
            'This method is not supported by MongoDB',
            [[['name' => 'Jane']], fn (QueryBuilder $builder) => $builder],
        ];
    }
}
