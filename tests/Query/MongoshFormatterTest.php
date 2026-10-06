<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Query;

use MongoDB\BSON\Binary;
use MongoDB\BSON\Decimal128;
use MongoDB\BSON\Int64;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use MongoDB\BSON\Timestamp;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Laravel\Query\MongoshFormatter;
use PHPUnit\Framework\TestCase;
use stdClass;

use function hex2bin;

class MongoshFormatterTest extends TestCase
{
    public function testFindRendersACollectionHelper(): void
    {
        $filter = new stdClass();
        $filter->name = 'test';

        self::assertSame(
            'db.getCollection("items").find({name: "test"})',
            MongoshFormatter::format(['find' => 'items', 'filter' => $filter]),
        );
    }

    public function testFindOmitsTheDriverSingleBatchFlag(): void
    {
        self::assertSame(
            'db.getCollection("items").find({}).limit(1)',
            MongoshFormatter::format([
                'find' => 'items',
                'filter' => new stdClass(),
                'limit' => 1,
                'singleBatch' => true,
            ]),
        );
    }

    public function testFindChainsSortSkipAndLimit(): void
    {
        $sort = new stdClass();
        $sort->name = 1;
        $projection = new stdClass();
        $projection->name = true;

        self::assertSame(
            'db.getCollection("items").find({}, {name: true}).sort({name: 1}).skip(1).limit(5)',
            MongoshFormatter::format([
                'find' => 'items',
                'filter' => new stdClass(),
                'projection' => $projection,
                'limit' => 5,
                'skip' => 1,
                'sort' => $sort,
            ]),
        );
    }

    public function testInsertOneUsesAnObjectIdConstructor(): void
    {
        $id = new ObjectId('507f1f77bcf86cd799439011');
        $document = new stdClass();
        $document->name = 'test';
        $document->_id = $id;

        self::assertSame(
            'db.getCollection("items").insertOne({name: "test", _id: ObjectId("507f1f77bcf86cd799439011")})',
            MongoshFormatter::format([
                'insert' => 'items',
                'ordered' => true,
                'documents' => [$document],
            ]),
        );
    }

    public function testUpdateManyAndDeleteMany(): void
    {
        $query = new stdClass();
        $query->name = 'test';
        $set = new stdClass();
        $set->name = 'changed';
        $update = new stdClass();
        $update->{'$set'} = $set;

        self::assertSame(
            'db.getCollection("items").updateMany({name: "test"}, {$set: {name: "changed"}})',
            MongoshFormatter::format([
                'update' => 'items',
                'ordered' => true,
                'updates' => [(object) ['q' => $query, 'u' => $update, 'upsert' => false, 'multi' => true]],
            ]),
        );

        self::assertSame(
            'db.getCollection("items").deleteMany({name: "test"})',
            MongoshFormatter::format([
                'delete' => 'items',
                'ordered' => true,
                'deletes' => [(object) ['q' => $query, 'limit' => 0]],
            ]),
        );
    }

    public function testCountDocumentsPipelineBecomesAHelper(): void
    {
        $match = new stdClass();
        $match->name = 'test';
        $group = new stdClass();
        $group->_id = 1;
        $group->n = (object) ['$sum' => 1];

        self::assertSame(
            'db.getCollection("items").countDocuments({name: "test"})',
            MongoshFormatter::format([
                'aggregate' => 'items',
                'pipeline' => [
                    (object) ['$match' => $match],
                    (object) ['$group' => $group],
                ],
                'cursor' => new stdClass(),
            ]),
        );
    }

    public function testAggregatePipelineStaysAnAggregate(): void
    {
        self::assertSame(
            'db.getCollection("items").aggregate([{$match: {name: "test"}}])',
            MongoshFormatter::format([
                'aggregate' => 'items',
                'pipeline' => [
                    ['$match' => ['name' => 'test']],
                ],
                'cursor' => new stdClass(),
            ]),
        );
    }

    public function testBsonValuesUseShellConstructors(): void
    {
        $document = [
            '_id' => new ObjectId('507f1f77bcf86cd799439011'),
            'when' => new UTCDateTime(0),
            'amount' => new Decimal128('1.50'),
            'big' => new Int64('9223372036854775807'),
            'pattern' => new Regex('a/b', 'i'),
            'stamp' => new Timestamp(1, 2),
            'uuid' => new Binary(hex2bin('00112233445566778899aabbccddeeff'), Binary::TYPE_UUID),
        ];

        self::assertSame(
            'db.getCollection("items").insertOne({_id: ObjectId("507f1f77bcf86cd799439011"), when: ISODate("1970-01-01T00:00:00.000Z"), amount: NumberDecimal("1.50"), big: NumberLong("9223372036854775807"), pattern: RegExp("a/b", "i"), stamp: Timestamp(2, 1), uuid: UUID("00112233-4455-6677-8899-aabbccddeeff")})',
            MongoshFormatter::format([
                'insert' => 'items',
                'documents' => [(object) $document],
            ]),
        );
    }

    public function testCollectionNamesThatAreNotIdentifiersAreQuoted(): void
    {
        self::assertSame(
            'db.getCollection("orders.archive").find({})',
            MongoshFormatter::format(['find' => 'orders.archive', 'filter' => new stdClass()]),
        );
    }

    public function testUnrecognizedCommandsStayExecutableExtendedJson(): void
    {
        $logged = MongoshFormatter::format(['ping' => 1]);

        self::assertStringStartsWith('db.runCommand(EJSON.parse(', $logged);
        self::assertStringContainsString('ping', $logged);
        self::assertStringEndsWith('))', $logged);
    }

    public function testFindAndModifyUpdateAndDelete(): void
    {
        $query = (object) ['name' => 'a'];
        $update = (object) ['$set' => (object) ['n' => 2]];

        self::assertSame(
            'db.getCollection("items").findOneAndUpdate({name: "a"}, {$set: {n: 2}}, {returnDocument: "after"})',
            MongoshFormatter::format([
                'findAndModify' => 'items',
                'query' => $query,
                'update' => $update,
                'new' => true,
            ]),
        );

        self::assertSame(
            'db.getCollection("items").findOneAndDelete({name: "a"})',
            MongoshFormatter::format([
                'findAndModify' => 'items',
                'query' => $query,
                'remove' => true,
            ]),
        );
    }

    public function testIndexAndCountHelpers(): void
    {
        self::assertSame(
            'db.getCollection("items").createIndex({name: 1}, {name: "name_1"})',
            MongoshFormatter::format([
                'createIndexes' => 'items',
                'indexes' => [(object) ['key' => (object) ['name' => 1], 'name' => 'name_1']],
            ]),
        );

        self::assertSame(
            'db.getCollection("items").dropIndex("name_1")',
            MongoshFormatter::format(['dropIndexes' => 'items', 'index' => 'name_1']),
        );

        self::assertSame(
            'db.getCollection("items").estimatedDocumentCount()',
            MongoshFormatter::format(['count' => 'items']),
        );
    }

    public function testUnorderedInsertMany(): void
    {
        self::assertSame(
            'db.getCollection("items").insertMany([{name: "a"}, {name: "b"}], {ordered: false})',
            MongoshFormatter::format([
                'insert' => 'items',
                'ordered' => false,
                'documents' => [(object) ['name' => 'a'], (object) ['name' => 'b']],
            ]),
        );
    }
}
