<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Casts;

use ArrayObject;
use LogicException;
use MongoDB\BSON\Decimal128;
use MongoDB\BSON\Int64;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Laravel\Tests\Models\EncryptedCasting;
use MongoDB\Laravel\Tests\Models\Patient;
use MongoDB\Laravel\Tests\TestCase;
use PHPUnit\Framework\Attributes\TestWith;

use function config;

/**
 * The casts derived from the encryptedFieldsMap and the write coercion to the
 * native BSON types. These tests do not need a server: only the connection
 * configuration is read.
 */
final class EncryptedFieldCastsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The map below is read by resolving the connection, which builds the
        // auto encryption options and raises on an extension that cannot
        // resolve a keyAltName.
        $this->skipIfKeyAltNameIsNotSupported();

        $this->enableEncryption([
            'encrypted_casting' => [
                'fields' => [
                    'an_int' => 'int',
                    'a_long' => 'long',
                    'a_double' => 'double',
                    'a_decimal' => 'decimal',
                    'a_bool' => 'bool',
                    'a_date' => 'date',
                    'an_object' => 'object',
                    'a_string' => 'string',
                    'billing.amount' => 'int',
                    'billing.due' => 'date',
                    'billing.label' => 'string',
                ],
            ],
        ]);
    }

    public function testDerivesCastsFromBsonType(): void
    {
        $casts = (new EncryptedCasting())->getCasts();

        self::assertSame('integer', $casts['an_int']);
        self::assertSame('integer', $casts['a_long']);
        self::assertSame('float', $casts['a_double']);
        self::assertSame('boolean', $casts['a_bool']);
        // A BSON date holds a full timestamp: the datetime cast keeps the time.
        self::assertSame('datetime', $casts['a_date']);

        // The driver type map already returns embedded documents as arrays and
        // strings as strings, so no cast is derived for these. A bare "decimal"
        // cast is invalid, so none is derived either.
        self::assertArrayNotHasKey('an_object', $casts);
        self::assertArrayNotHasKey('a_string', $casts);
        self::assertArrayNotHasKey('a_decimal', $casts);
    }

    public function testCoercesStringValuesToNativeBsonTypes(): void
    {
        $model = new EncryptedCasting();
        $model->an_int = '1500';
        $model->a_long = '1500';
        $model->a_double = '15.5';
        $model->a_decimal = '1500.25';
        $model->a_bool = '1';
        $model->a_date = '2020-01-02';
        $model->an_object = (object) ['foo' => 'bar'];

        $attributes = $model->getAttributes();

        self::assertSame(1500, $attributes['an_int']);
        self::assertInstanceOf(Int64::class, $attributes['a_long']);
        self::assertSame('1500', (string) $attributes['a_long']);
        self::assertSame(15.5, $attributes['a_double']);
        self::assertInstanceOf(Decimal128::class, $attributes['a_decimal']);
        self::assertSame('1500.25', (string) $attributes['a_decimal']);
        self::assertTrue($attributes['a_bool']);
        self::assertInstanceOf(UTCDateTime::class, $attributes['a_date']);
        self::assertSame(['foo' => 'bar'], $attributes['an_object']);
    }

    #[TestWith(['false', false])]
    #[TestWith(['true', true])]
    #[TestWith(['0', false])]
    #[TestWith(['1', true])]
    #[TestWith([1, true])]
    public function testBooleanValuesAreParsed(bool|int|string $value, bool $expected): void
    {
        $model = new EncryptedCasting();
        $model->a_bool = $value;

        self::assertSame($expected, $model->getAttributes()['a_bool']);
    }

    #[TestWith(['an_int'])]
    #[TestWith(['a_long'])]
    #[TestWith(['a_double'])]
    #[TestWith(['a_decimal'])]
    #[TestWith(['a_bool'])]
    public function testValuesThatCannotBeConvertedAreLeftUntouched(string $attribute): void
    {
        // Automatic encryption rejects them with its own message, which is
        // better than silently storing 0 or an empty string.
        $model = new EncryptedCasting();
        $model->$attribute = 'abc';

        self::assertSame('abc', $model->getAttributes()[$attribute]);
    }

    public function testReadsBackTheNativePhpTypes(): void
    {
        $model = new EncryptedCasting();
        $model->an_int = '1500';
        $model->a_long = '1500';
        $model->a_double = '15.5';
        $model->a_bool = '1';
        $model->a_date = '2020-01-02 15:30:00';

        self::assertSame(1500, $model->an_int);
        self::assertSame(1500, $model->a_long);
        self::assertSame(15.5, $model->a_double);
        self::assertTrue($model->a_bool);
        // The datetime cast keeps the time of the stored timestamp.
        self::assertSame('2020-01-02 15:30:00', $model->a_date->format('Y-m-d H:i:s'));
    }

    public function testNullValuesAreNotCoerced(): void
    {
        $model = new EncryptedCasting();
        $model->an_int = null;
        $model->a_long = null;
        $model->a_date = null;

        self::assertNull($model->getAttributes()['an_int']);
        self::assertNull($model->getAttributes()['a_long']);
        self::assertNull($model->getAttributes()['a_date']);
    }

    public function testNoCastIsDerivedForADottedEncryptedPath(): void
    {
        // An Eloquent cast is keyed by a plain attribute name, so a nested
        // encrypted field is never cast. The driver returns the native value.
        $casts = (new EncryptedCasting())->getCasts();

        self::assertArrayNotHasKey('billing', $casts);
        self::assertArrayNotHasKey('billing.amount', $casts);
        self::assertArrayNotHasKey('billing.due', $casts);
    }

    public function testCoercesEncryptedFieldsOfAnEmbeddedDocument(): void
    {
        $model = new EncryptedCasting();
        $model->billing = [
            'amount' => '1500',
            'due' => '2020-01-02',
            'label' => 'invoice',
            // Not declared in the map: left untouched.
            'note' => '42',
        ];

        $billing = $model->getAttributes()['billing'];

        self::assertSame(1500, $billing['amount']);
        self::assertInstanceOf(UTCDateTime::class, $billing['due']);
        self::assertSame('invoice', $billing['label']);
        self::assertSame('42', $billing['note']);
    }

    public function testCoercesEncryptedFieldsOfAnEmbeddedObject(): void
    {
        $model = new EncryptedCasting();
        $model->billing = (object) ['amount' => '1500'];

        self::assertSame(1500, $model->getAttributes()['billing']['amount']);
    }

    public function testEmbeddedDocumentAssignedAsAnotherObjectIsNotWalked(): void
    {
        // Only an array or a stdClass is walked. Any other object is a value of
        // its own: it is left untouched rather than mangled by a cast to array.
        $billing = new ArrayObject(['amount' => '1500']);

        $model = new EncryptedCasting();
        $model->billing = $billing;

        self::assertSame($billing, $model->getAttributes()['billing']);
    }

    public function testCoercesEncryptedFieldSetByDottedPath(): void
    {
        $model = new EncryptedCasting();
        $model->setAttribute('billing.amount', '1500');

        self::assertSame(1500, $model->getAttributes()['billing']['amount']);
    }

    public function testNullEmbeddedDocumentIsNotCoerced(): void
    {
        $model = new EncryptedCasting();
        $model->billing = null;

        self::assertNull($model->getAttributes()['billing']);
    }

    public function testEmbeddedDocumentWithoutEncryptedSubFieldIsNotCoerced(): void
    {
        $model = new EncryptedCasting();
        $model->an_object = ['amount' => '1500'];

        // "an_object" is a whole encrypted document, not a parent of the
        // "billing" sub-fields, so its sub-values are left untouched.
        self::assertSame(['amount' => '1500'], $model->getAttributes()['an_object']);
    }

    public function testNoCastIsDerivedWhenTheCollectionIsNotEncrypted(): void
    {
        config(['database.connections.mongodb.driver_options.autoEncryption' => null]);
        $this->app->make('db')->purge('mongodb');

        $casts = (new EncryptedCasting())->getCasts();

        self::assertArrayNotHasKey('an_int', $casts);
        self::assertArrayNotHasKey('a_date', $casts);
    }

    public function testCompatibleDeclaredCastIsKept(): void
    {
        $model = new EncryptedCasting();
        $model->mergeCasts(['an_int' => 'integer']);

        self::assertSame('integer', $model->getCasts()['an_int']);
    }

    public function testCustomCastOwnsItsValue(): void
    {
        // A custom cast is the developer's responsibility: the value is handed
        // to it untouched, not coerced first.
        RecordingCast::$received = null;

        $model = new EncryptedCasting();
        $model->mergeCasts(['an_int' => RecordingCast::class]);
        $model->an_int = '1500';

        self::assertSame('1500', RecordingCast::$received);
    }

    public function testDecimalCastWithAScaleIsKept(): void
    {
        $model = new EncryptedCasting();
        $model->mergeCasts(['a_decimal' => 'decimal:2']);

        self::assertSame('decimal:2', $model->getCasts()['a_decimal']);
    }

    #[TestWith(['decimal'])]
    #[TestWith(['decimal:'])]
    #[TestWith(['decimal:abc'])]
    #[TestWith(['decimal:2.5'])]
    public function testDecimalCastWithoutAScaleIsReported(string $cast): void
    {
        // Without a scale, the cast cannot convert a value: Laravel passes the
        // cast string itself as the scale.
        $model = new EncryptedCasting();
        $model->mergeCasts(['a_decimal' => $cast]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('does not store the "decimal" BSON type');

        $model->getCasts();
    }

    public function testIncompatibleDeclaredCastIsReported(): void
    {
        $model = new EncryptedCasting();
        $model->mergeCasts(['an_int' => 'string']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('does not store the "int" BSON type');

        $model->getCasts();
    }

    public function testExistingModelWithCompatibleCastIsNotReported(): void
    {
        // Patient declares "billing_amount" => "integer", compatible with the
        // "int" bsonType of the map.
        $this->enableEncryption([
            'patients' => [
                'fields' => [
                    'ssn' => 'string',
                    'billing_amount' => 'int',
                    'billing' => 'object',
                ],
            ],
        ]);

        $patient = new Patient();

        self::assertSame('integer', $patient->getCasts()['billing_amount']);
    }
}
