<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Casts;

use Illuminate\Support\Exceptions\MathException;
use MongoDB\BSON\Binary;
use MongoDB\BSON\Decimal128;
use MongoDB\BSON\Int64;
use MongoDB\BSON\Javascript;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Laravel\Collection;
use MongoDB\Laravel\Tests\Models\Casting;
use MongoDB\Laravel\Tests\TestCase;

use function now;

class DecimalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Casting::truncate();
    }

    public function testDecimal(): void
    {
        $model = Casting::query()->create(['decimalNumber' => 100.99]);

        self::assertIsString($model->decimalNumber);
        self::assertInstanceOf(Decimal128::class, $model->getRawOriginal('decimalNumber'));
        self::assertEquals('100.99', $model->decimalNumber);

        $model->update(['decimalNumber' => 9999.9]);

        self::assertIsString($model->decimalNumber);
        self::assertInstanceOf(Decimal128::class, $model->getRawOriginal('decimalNumber'));
        self::assertEquals('9999.90', $model->decimalNumber);

        $model->update(['decimalNumber' => 9999.00000009]);

        self::assertIsString($model->decimalNumber);
        self::assertInstanceOf(Decimal128::class, $model->getRawOriginal('decimalNumber'));
        self::assertEquals('9999.00', $model->decimalNumber);
    }

    public function testDecimalAsString(): void
    {
        $model = Casting::query()->create(['decimalNumber' => '120.79']);

        self::assertIsString($model->decimalNumber);
        self::assertInstanceOf(Decimal128::class, $model->getRawOriginal('decimalNumber'));
        self::assertEquals('120.79', $model->decimalNumber);

        $model->update(['decimalNumber' => '795']);

        self::assertIsString($model->decimalNumber);
        self::assertInstanceOf(Decimal128::class, $model->getRawOriginal('decimalNumber'));
        self::assertEquals('795.00', $model->decimalNumber);

        $model->update(['decimalNumber' => '1234.99999999999']);

        self::assertIsString($model->decimalNumber);
        self::assertInstanceOf(Decimal128::class, $model->getRawOriginal('decimalNumber'));
        self::assertEquals('1235.00', $model->decimalNumber);
    }

    public function testDecimalAsDecimal128(): void
    {
        $model = Casting::query()->create(['decimalNumber' => new Decimal128('100.99')]);

        self::assertIsString($model->decimalNumber);
        self::assertInstanceOf(Decimal128::class, $model->getRawOriginal('decimalNumber'));
        self::assertEquals('100.99', $model->decimalNumber);

        $model->update(['decimalNumber' => new Decimal128('9999.9')]);

        self::assertIsString($model->decimalNumber);
        self::assertInstanceOf(Decimal128::class, $model->getRawOriginal('decimalNumber'));
        self::assertEquals('9999.90', $model->decimalNumber);
    }

    public function testOtherBSONTypes(): void
    {
        $modelId = $this->setBSONType(new Int64(100));
        $model = Casting::query()->find($modelId);

        self::assertIsString($model->decimalNumber);
        self::assertIsInt($model->getRawOriginal('decimalNumber'));
        self::assertEquals('100.00', $model->decimalNumber);

        // Update decimalNumber to a Binary type
        $this->setBSONType(new Binary('100.1234', Binary::TYPE_GENERIC), $modelId);
        $model->refresh();

        self::assertIsString($model->decimalNumber);
        self::assertInstanceOf(Binary::class, $model->getRawOriginal('decimalNumber'));
        self::assertEquals('100.12', $model->decimalNumber);

        $this->setBSONType(new Javascript('function() { return 100; }'), $modelId);
        $model->refresh();
        self::expectException(MathException::class);
        self::expectExceptionMessage('Unable to cast value to a decimal.');
        $model->decimalNumber;
        self::assertInstanceOf(Javascript::class, $model->getRawOriginal('decimalNumber'));

        $this->setBSONType(new UTCDateTime(now()), $modelId);
        $model->refresh();
        self::expectException(MathException::class);
        self::expectExceptionMessage('Unable to cast value to a decimal.');
        $model->decimalNumber;
        self::assertInstanceOf(UTCDateTime::class, $model->getRawOriginal('decimalNumber'));
    }

    private function setBSONType($value, $id = null)
    {
        // Do a raw insert/update, so we can enforce the type we want
        return Casting::raw(function (Collection $collection) use ($id, $value) {
            if (! empty($id)) {
                return $collection->updateOne(
                    ['_id' => $id], // "id" is not translated to "_id" by the raw method
                    ['$set' => ['decimalNumber' => $value]],
                );
            }

            return $collection->insertOne(['decimalNumber' => $value])->getInsertedId();
        });
    }
}
