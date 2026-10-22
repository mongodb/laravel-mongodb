<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Encryption;

use MongoDB\BSON\Binary;
use MongoDB\BSON\Decimal128;
use MongoDB\BSON\Int64;
use MongoDB\Laravel\Encryption\CollectionState;
use MongoDB\Laravel\Encryption\CollectionStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_is_list;
use function array_map;
use function ini_get;
use function ini_set;
use function is_array;

/**
 * The comparison of the encrypted fields declared in the configuration with
 * the ones stored with the collection.
 */
class CollectionStatusTest extends TestCase
{
    private const KEY_ID = 'aaaabbbbccccdddd';

    private const OTHER_KEY_ID = 'eeeeffff00001111';

    private const EQUALITY_QUERY = ['queryType' => 'equality'];

    private const RANGE_QUERY = ['queryType' => 'range', 'min' => 0, 'max' => 100, 'sparsity' => 1];

    private const SSN_FIELD = ['path' => 'ssn', 'bsonType' => 'string', 'queries' => [self::EQUALITY_QUERY]];

    private const EMAIL_FIELD = ['path' => 'email', 'bsonType' => 'string'];

    private const AMOUNT_FIELD = ['path' => 'amount', 'bsonType' => 'int', 'queries' => [self::RANGE_QUERY]];

    /** @return array<string, array{list<array<string, mixed>>, list<array<string, mixed>>, CollectionState, list<string>}> */
    public static function provideEncryptedFields(): array
    {
        $search = static fn (bool $caseSensitive, int|float $maxLength = 50): array => [
            [
                'path' => 'ssn',
                'bsonType' => 'string',
                'queries' => [['queryType' => 'substringPreview', 'strMinQueryLength' => 2, 'strMaxQueryLength' => 10, 'strMaxLength' => $maxLength, 'caseSensitive' => $caseSensitive]],
            ],
        ];

        return [
            // The configuration names the data key with a keyAltName, and the
            // collection stores the resolved keyId: it is not compared.
            'same fields' => [
                [self::SSN_FIELD, self::AMOUNT_FIELD],
                [self::SSN_FIELD, self::AMOUNT_FIELD],
                CollectionState::Encrypted,
                [],
            ],
            'another field order' => [
                [self::SSN_FIELD, self::AMOUNT_FIELD],
                [self::AMOUNT_FIELD, self::SSN_FIELD],
                CollectionState::Encrypted,
                [],
            ],
            'another query order' => [
                [['path' => 'amount', 'bsonType' => 'int', 'queries' => [self::RANGE_QUERY, self::EQUALITY_QUERY]]],
                [['path' => 'amount', 'bsonType' => 'int', 'queries' => [self::EQUALITY_QUERY, self::RANGE_QUERY]]],
                CollectionState::Encrypted,
                [],
            ],
            // A field that names the data key with a keyId: the stored keyId
            // has to be the same one.
            'same keyId' => [
                [self::SSN_FIELD + ['keyId' => self::keyId()]],
                [self::SSN_FIELD + ['keyId' => self::keyId()]],
                CollectionState::Encrypted,
                [],
            ],
            'another keyId' => [
                [self::SSN_FIELD + ['keyId' => self::keyId()]],
                [self::SSN_FIELD + ['keyId' => self::keyId(self::OTHER_KEY_ID)]],
                CollectionState::Mismatched,
                ['ssn'],
            ],
            // A keyAltName is resolved by the server, so the stored keyId
            // cannot be checked against it.
            'keyAltName ignores the keyId' => [
                [self::SSN_FIELD],
                [self::SSN_FIELD + ['keyId' => self::keyId(self::OTHER_KEY_ID)]],
                CollectionState::Encrypted,
                [],
            ],
            // The collection stores the contention of every query, whether the
            // configuration declares one or not, hence the stored 8 here.
            'another contention' => [
                [['path' => 'ssn', 'bsonType' => 'string', 'queries' => [self::EQUALITY_QUERY + ['contention' => 4]]]],
                [['path' => 'ssn', 'bsonType' => 'string', 'queries' => [self::EQUALITY_QUERY]]],
                CollectionState::Mismatched,
                ['ssn'],
            ],
            // The server stores an integer option declared as a float or a
            // decimal as an integer, so the declared type is not a difference.
            'contention declared as a float' => [
                [['path' => 'ssn', 'bsonType' => 'string', 'queries' => [self::EQUALITY_QUERY + ['contention' => 4.0]]]],
                [['path' => 'ssn', 'bsonType' => 'string', 'queries' => [self::EQUALITY_QUERY + ['contention' => 4]]]],
                CollectionState::Encrypted,
                [],
            ],
            'contention declared as a decimal' => [
                [['path' => 'ssn', 'bsonType' => 'string', 'queries' => [self::EQUALITY_QUERY + ['contention' => new Decimal128('4')]]]],
                [['path' => 'ssn', 'bsonType' => 'string', 'queries' => [self::EQUALITY_QUERY + ['contention' => 4]]]],
                CollectionState::Encrypted,
                [],
            ],
            // A field can declare a single query document instead of a list of
            // queries, and the collection stores the shape it was given.
            'single query document' => [
                [['path' => 'ssn', 'bsonType' => 'string', 'queries' => self::EQUALITY_QUERY]],
                [['path' => 'ssn', 'bsonType' => 'string', 'queries' => self::EQUALITY_QUERY]],
                CollectionState::Encrypted,
                [],
            ],
            'single query document against a list of queries' => [
                [['path' => 'ssn', 'bsonType' => 'string', 'queries' => self::EQUALITY_QUERY]],
                [['path' => 'ssn', 'bsonType' => 'string', 'queries' => [self::EQUALITY_QUERY]]],
                CollectionState::Encrypted,
                [],
            ],
            'another contention in a single query document' => [
                [['path' => 'ssn', 'bsonType' => 'string', 'queries' => self::EQUALITY_QUERY + ['contention' => 4]]],
                [['path' => 'ssn', 'bsonType' => 'string', 'queries' => self::EQUALITY_QUERY]],
                CollectionState::Mismatched,
                ['ssn'],
            ],
            'same trimFactor' => [
                [['path' => 'amount', 'bsonType' => 'int', 'queries' => [self::RANGE_QUERY + ['trimFactor' => 0]]]],
                [['path' => 'amount', 'bsonType' => 'int', 'queries' => [self::RANGE_QUERY + ['trimFactor' => 0]]]],
                CollectionState::Encrypted,
                [],
            ],
            'another trimFactor' => [
                [['path' => 'amount', 'bsonType' => 'int', 'queries' => [self::RANGE_QUERY + ['trimFactor' => 1]]]],
                [['path' => 'amount', 'bsonType' => 'int', 'queries' => [self::RANGE_QUERY]]],
                CollectionState::Mismatched,
                ['amount'],
            ],
            // Every option is compared, so an option that a later server
            // version adds is a difference without a change here.
            'query option not declared' => [
                [self::SSN_FIELD],
                [['path' => 'ssn', 'bsonType' => 'string', 'queries' => [self::EQUALITY_QUERY + ['futureOption' => true]]]],
                CollectionState::Mismatched,
                ['ssn'],
            ],
            'field declared but not stored' => [
                [self::SSN_FIELD, self::EMAIL_FIELD],
                [self::SSN_FIELD],
                CollectionState::Mismatched,
                ['email'],
            ],
            'field stored but not declared' => [
                [self::SSN_FIELD],
                [self::SSN_FIELD, self::EMAIL_FIELD],
                CollectionState::Mismatched,
                ['email'],
            ],
            'another bsonType' => [
                [self::SSN_FIELD],
                [['path' => 'ssn', 'bsonType' => 'int', 'queries' => [self::EQUALITY_QUERY]]],
                CollectionState::Mismatched,
                ['ssn'],
            ],
            'another range bounds' => [
                [self::AMOUNT_FIELD],
                [['path' => 'amount', 'bsonType' => 'int', 'queries' => [['queryType' => 'range', 'min' => 0, 'max' => 200, 'sparsity' => 1]]]],
                CollectionState::Mismatched,
                ['amount'],
            ],
            'another query type' => [
                [self::SSN_FIELD],
                [['path' => 'ssn', 'bsonType' => 'string']],
                CollectionState::Mismatched,
                ['ssn'],
            ],
            // The string preview queries of the server 8.2 and later.
            'same search options' => [$search(true), $search(true), CollectionState::Encrypted, []],
            'another search option' => [$search(true), $search(false), CollectionState::Mismatched, ['ssn']],
            'search length declared as a float' => [$search(true, 50.0), $search(true, 50), CollectionState::Encrypted, []],
            'differing fields are sorted' => [
                [self::SSN_FIELD, self::EMAIL_FIELD, self::AMOUNT_FIELD],
                [self::AMOUNT_FIELD],
                CollectionState::Mismatched,
                ['email', 'ssn'],
            ],
            'no field stored' => [
                [self::SSN_FIELD],
                [],
                CollectionState::Mismatched,
                ['ssn'],
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $declared
     * @param list<array<string, mixed>> $stored
     * @param list<string>               $differing
     */
    #[DataProvider('provideEncryptedFields')]
    public function testCompareEncryptedFields(array $declared, array $stored, CollectionState $state, array $differing): void
    {
        $status = CollectionStatus::fromEncryptedFields(
            self::declaredFields($declared),
            ['fields' => self::storedFields($stored)],
        );

        self::assertSame($state, $status->state);
        self::assertSame($differing, $status->differingFields);
    }

    /**
     * The bound of a "long" range field has to be declared as an Int64, because
     * the server rejects a PHP int there, while the driver reads the stored
     * 64-bit integer back as a PHP int when it fits one. The stored shape below
     * is the one the server holds for a collection created with the declaration.
     */
    public function testLongRangeBounds(): void
    {
        $declared = [['path' => 'amount', 'bsonType' => 'long', 'keyAltName' => 'unittest.patients/amount', 'queries' => [['queryType' => 'range', 'min' => new Int64(0), 'max' => new Int64(1000)]]]];
        $stored = ['fields' => [['path' => 'amount', 'bsonType' => 'long', 'keyId' => new Binary('aaaabbbbccccdddd', Binary::TYPE_UUID), 'queries' => [['queryType' => 'range', 'contention' => 8, 'min' => 0, 'max' => 1000]]]]];

        $status = CollectionStatus::fromEncryptedFields($declared, $stored);

        self::assertSame(CollectionState::Encrypted, $status->state);
        self::assertSame([], $status->differingFields);
    }

    /**
     * The bounds are compared exactly, even when "serialize_precision" is 14 and
     * json_encode() would round two different doubles to the same value.
     */
    public function testDoubleRangeBoundsAreComparedExactly(): void
    {
        $declared = [['path' => 'amount', 'bsonType' => 'double', 'keyAltName' => 'unittest.patients/amount', 'queries' => [['queryType' => 'range', 'min' => 0.0, 'max' => 0.3]]]];
        $stored = ['fields' => [['path' => 'amount', 'bsonType' => 'double', 'keyId' => new Binary('aaaabbbbccccdddd', Binary::TYPE_UUID), 'queries' => [['queryType' => 'range', 'contention' => 8, 'min' => 0.0, 'max' => 0.30000000000000004]]]]];

        $precision = (string) ini_get('serialize_precision');
        ini_set('serialize_precision', '14');

        try {
            $status = CollectionStatus::fromEncryptedFields($declared, $stored);
        } finally {
            ini_set('serialize_precision', $precision);
        }

        self::assertSame(CollectionState::Mismatched, $status->state);
        self::assertSame(['amount'], $status->differingFields);
    }

    /** @return array<string, array{CollectionState, bool}> */
    public static function provideNeedsRecreate(): array
    {
        return [
            'encrypted' => [CollectionState::Encrypted, false],
            'not encrypted' => [CollectionState::NotEncrypted, true],
            'fields differ' => [CollectionState::Mismatched, true],
            'missing' => [CollectionState::Missing, false],
        ];
    }

    #[DataProvider('provideNeedsRecreate')]
    public function testNeedsRecreate(CollectionState $state, bool $expected): void
    {
        self::assertSame($expected, (new CollectionStatus($state))->needsRecreate());
    }

    private static function keyId(string $hex = self::KEY_ID): Binary
    {
        return new Binary($hex, Binary::TYPE_UUID);
    }

    /**
     * The configured form of the fields: a field that does not declare a keyId
     * names the data key with an alternate name, as the normalization does.
     *
     * @param  list<array<string, mixed>> $fields
     *
     * @return list<array<string, mixed>>
     */
    private static function declaredFields(array $fields): array
    {
        return array_map(
            static fn (array $field): array => isset($field['keyId'])
                ? $field
                : $field + ['keyAltName' => 'unittest.patients/' . $field['path']],
            $fields,
        );
    }

    /**
     * The stored form of the fields: the collection holds the resolved keyId
     * and the contention of every query.
     *
     * @param  list<array<string, mixed>> $fields
     *
     * @return list<array<string, mixed>>
     */
    private static function storedFields(array $fields): array
    {
        return array_map(
            static function (array $field): array {
                $field['keyId'] ??= self::keyId();

                if (isset($field['queries']) && is_array($field['queries'])) {
                    // A field declared with a single query document stores that
                    // document, not a one-element list of queries.
                    $field['queries'] = array_is_list($field['queries'])
                        ? array_map(static fn (array $query): array => $query + ['contention' => 8], $field['queries'])
                        : $field['queries'] + ['contention' => 8];
                }

                return $field;
            },
            $fields,
        );
    }
}
