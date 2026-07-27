<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Query;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Laravel\Connection;
use MongoDB\Laravel\Query\Grammar;
use MongoDB\Laravel\Tests\TestCase;
use stdClass;

use function date_default_timezone_get;
use function property_exists;

class GrammarTest extends TestCase
{
    private Grammar $grammar;
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->getMongoConnection();
        $this->grammar    = new Grammar($this->connection);
    }

    /**
     * Test that id is converted to _id for root level queries
     */
    public function testPrepareFieldsForQueryConvertsIdToUnderscoreId()
    {
        $input = ['id' => 123, 'name' => 'John'];
        $result = $this->grammar->prepareFieldsForQuery($input);

        $this->assertArrayHasKey('_id', $result);
        $this->assertArrayNotHasKey('id', $result);
        $this->assertEquals(123, $result['_id']);
        $this->assertEquals('John', $result['name']);
    }

    /**
     * Test that embedded id fields are converted based on configuration
     */
    public function testPrepareFieldsForQueryHandlesEmbeddedIdFields()
    {
        // With renameEmbeddedIdField enabled (default)
        $input = ['user' => ['id' => 456, 'name' => 'Jane']];
        $result = $this->grammar->prepareFieldsForQuery($input);

        $this->assertEquals(['user' => ['_id' => 456, 'name' => 'Jane']], $result);
    }

    /**
     * Test that embedded id fields are NOT converted when configuration is disabled
     */
    public function testPrepareFieldsForQueryRespectsRenameEmbeddedIdFieldConfiguration()
    {
        $this->connection->setRenameEmbeddedIdField(false);

        $input = ['user' => ['id' => 456, 'name' => 'Jane']];
        $result = $this->grammar->prepareFieldsForQuery($input);

        // Embedded id should NOT be renamed
        $this->assertEquals(['user' => ['id' => 456, 'name' => 'Jane']], $result);

        // Reset for other tests
        $this->connection->setRenameEmbeddedIdField(true);
    }

    /**
     * Test that arrow notation is converted to dot notation
     */
    public function testPrepareFieldsForQueryConvertsArrowNotationToDotNotation()
    {
        $input = ['user->name' => 'John', 'user->email' => 'john@example.com'];
        $result = $this->grammar->prepareFieldsForQuery($input);

        $this->assertArrayHasKey('user.name', $result);
        $this->assertArrayHasKey('user.email', $result);
        $this->assertArrayNotHasKey('user->name', $result);
        $this->assertArrayNotHasKey('user->email', $result);
        $this->assertEquals('John', $result['user.name']);
        $this->assertEquals('john@example.com', $result['user.email']);
    }

    /**
     * Test that .id subfields are converted to ._id
     */
    public function testPrepareFieldsForQueryConvertsSubfieldIdToUnderscoreId()
    {
        $input = ['user.id' => 789];
        $result = $this->grammar->prepareFieldsForQuery($input);

        $this->assertArrayHasKey('user._id', $result);
        $this->assertArrayNotHasKey('user.id', $result);
        $this->assertEquals(789, $result['user._id']);
    }

    /**
     * Test that .id subfields are NOT converted when configuration is disabled
     */
    public function testPrepareFieldsForQueryDoesNotConvertSubfieldIdWhenConfigDisabled()
    {
        $this->connection->setRenameEmbeddedIdField(false);

        $input = ['user.id' => 789];
        $result = $this->grammar->prepareFieldsForQuery($input);

        // Should NOT be converted when renameEmbeddedIdField is false
        $this->assertArrayHasKey('user.id', $result);
        $this->assertArrayNotHasKey('user._id', $result);
        $this->assertEquals(789, $result['user.id']);

        // Reset for other tests
        $this->connection->setRenameEmbeddedIdField(true);
    }

    /**
     * Test that DateTime instances are converted to UTCDateTime
     */
    public function testPrepareFieldsForQueryConvertsDateTimeToUTCDateTime()
    {
        $date = Carbon::parse('2024-01-01 12:00:00');
        $input = ['created_at' => $date, 'name' => 'Test'];
        $result = $this->grammar->prepareFieldsForQuery($input);

        $this->assertInstanceOf(UTCDateTime::class, $result['created_at']);
        $this->assertEquals('Test', $result['name']);
    }

    /**
     * Test that nested arrays are processed recursively
     */
    public function testPrepareFieldsForQueryProcessesNestedArrays()
    {
        $input = [
            'id' => 1,
            'nested' => [
                'id' => 2,
                'deep' => ['id' => 3],
            ],
        ];
        $result = $this->grammar->prepareFieldsForQuery($input);

        $this->assertEquals(1, $result['_id']);
        $this->assertEquals(2, $result['nested']['_id']);
        $this->assertEquals(3, $result['nested']['deep']['_id']);
    }

    /**
     * Test that nested arrays respect renameEmbeddedIdField configuration
     */
    public function testPrepareFieldsForQueryProcessesNestedArraysWithConfigDisabled()
    {
        $this->connection->setRenameEmbeddedIdField(false);

        $input = [
            'id' => 1,
            'nested' => [
                'id' => 2,
                'deep' => ['id' => 3],
            ],
        ];
        $result = $this->grammar->prepareFieldsForQuery($input);

        // Root level id should still be converted to _id
        $this->assertEquals(1, $result['_id']);
        // But nested id fields should NOT be converted
        $this->assertEquals(2, $result['nested']['id']);
        $this->assertEquals(3, $result['nested']['deep']['id']);

        // Reset for other tests
        $this->connection->setRenameEmbeddedIdField(true);
    }

    /**
     * Test that having both id and _id throws an exception
     */
    public function testPrepareFieldsForQueryThrowsExceptionWhenBothIdAndUnderscoreIdExist()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot have both "id" and "_id" fields.');

        $input = ['id' => 123, '_id' => 456];
        $this->grammar->prepareFieldsForQuery($input);
    }

    /**
     * Test that having both arrow notation and dot notation throws an exception
     */
    public function testPrepareFieldsForQueryThrowsExceptionForConflictingNotations()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot have both "user->name" and "user.name" fields.');

        $input = ['user->name' => 'John', 'user.name' => 'Jane'];
        $this->grammar->prepareFieldsForQuery($input);
    }

    /**
     * Test that stdClass values are recursed into and processed
     */
    public function testPrepareFieldsForQueryProcessesStdClassValues()
    {
        $metadata = new stdClass();
        $metadata->id = '123';
        $metadata->name = 'test';

        $input = ['metadata' => $metadata];
        $result = $this->grammar->prepareFieldsForQuery($input);

        $this->assertInstanceOf(stdClass::class, $result['metadata']);
        $this->assertTrue(property_exists($result['metadata'], '_id'));
        $this->assertFalse(property_exists($result['metadata'], 'id'));
        $this->assertEquals('123', $result['metadata']->_id);
        $this->assertEquals('test', $result['metadata']->name);
    }

    /**
     * Test that DateTimeInterface inside stdClass values are converted to UTCDateTime
     */
    public function testPrepareFieldsForQueryConvertsDateTimeInsideStdClass()
    {
        $metadata = new stdClass();
        $metadata->created_at = Carbon::parse('2024-01-01 12:00:00');
        $metadata->label = 'test';

        $input = ['metadata' => $metadata];
        $result = $this->grammar->prepareFieldsForQuery($input);

        $this->assertInstanceOf(stdClass::class, $result['metadata']);
        $this->assertInstanceOf(UTCDateTime::class, $result['metadata']->created_at);
        $this->assertEquals('test', $result['metadata']->label);
    }

    /**
     * Test that stdClass values respect renameEmbeddedIdField configuration
     */
    public function testPrepareFieldsForQueryProcessesStdClassWithConfigDisabled()
    {
        $this->connection->setRenameEmbeddedIdField(false);

        $metadata = new stdClass();
        $metadata->id = '123';
        $metadata->name = 'test';

        $input = ['metadata' => $metadata];
        $result = $this->grammar->prepareFieldsForQuery($input);

        $this->assertInstanceOf(stdClass::class, $result['metadata']);
        $this->assertTrue(property_exists($result['metadata'], 'id'));
        $this->assertFalse(property_exists($result['metadata'], '_id'));

        $this->connection->setRenameEmbeddedIdField(true);
    }

    /**
     * Test that _id is converted to id for root level results
     */
    public function testPrepareFieldsForResultConvertsUnderscoreIdToId()
    {
        $input = ['_id' => 123, 'name' => 'John'];
        $result = $this->grammar->prepareFieldsForResult($input);

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayNotHasKey('_id', $result);
        $this->assertEquals(123, $result['id']);
        $this->assertEquals('John', $result['name']);
    }

    /**
     * Test that embedded _id fields are converted based on configuration
     */
    public function testPrepareFieldsForResultHandlesEmbeddedIdFields()
    {
        $input = ['user' => ['_id' => 456, 'name' => 'Jane']];
        $result = $this->grammar->prepareFieldsForResult($input);

        $this->assertEquals(['user' => ['id' => 456, 'name' => 'Jane']], $result);
    }

    /**
     * Test that embedded _id fields are NOT converted when configuration is disabled
     */
    public function testPrepareFieldsForResultRespectsRenameEmbeddedIdFieldConfiguration()
    {
        $this->connection->setRenameEmbeddedIdField(false);

        $input = ['user' => ['_id' => 456, 'name' => 'Jane']];
        $result = $this->grammar->prepareFieldsForResult($input);

        // Embedded _id should NOT be renamed
        $this->assertEquals(['user' => ['_id' => 456, 'name' => 'Jane']], $result);

        // Reset for other tests
        $this->connection->setRenameEmbeddedIdField(true);
    }

    /**
     * Test that UTCDateTime instances are converted to Carbon with local timezone
     */
    public function testPrepareFieldsForResultConvertsUTCDateTimeToCarbon()
    {
        $utcDate = new UTCDateTime(Carbon::parse('2024-01-01 12:00:00', 'UTC'));
        $input = ['created_at' => $utcDate, 'name' => 'Test'];
        $result = $this->grammar->prepareFieldsForResult($input);

        $this->assertInstanceOf(Carbon::class, $result['created_at']);
        $this->assertEquals(date_default_timezone_get(), $result['created_at']->getTimezone()->getName());
        $this->assertEquals('Test', $result['name']);
    }

    /**
     * Test that nested arrays in results are processed recursively
     */
    public function testPrepareFieldsForResultProcessesNestedArrays()
    {
        $input = [
            '_id' => 1,
            'nested' => [
                '_id' => 2,
                'deep' => ['_id' => 3],
            ],
        ];
        $result = $this->grammar->prepareFieldsForResult($input);

        $this->assertEquals(1, $result['id']);
        $this->assertEquals(2, $result['nested']['id']);
        $this->assertEquals(3, $result['nested']['deep']['id']);
    }

    /**
     * Test that nested arrays in results respect renameEmbeddedIdField configuration
     */
    public function testPrepareFieldsForResultProcessesNestedArraysWithConfigDisabled()
    {
        $this->connection->setRenameEmbeddedIdField(false);

        $input = [
            '_id' => 1,
            'nested' => [
                '_id' => 2,
                'deep' => ['_id' => 3],
            ],
        ];
        $result = $this->grammar->prepareFieldsForResult($input);

        // Root level _id should still be converted to id
        $this->assertEquals(1, $result['id']);
        // But nested _id fields should NOT be converted
        $this->assertEquals(2, $result['nested']['_id']);
        $this->assertEquals(3, $result['nested']['deep']['_id']);

        // Reset for other tests
        $this->connection->setRenameEmbeddedIdField(true);
    }

    /**
     * Test that stdClass objects are handled correctly
     */
    public function testPrepareFieldsForResultHandlesStdClass()
    {
        $input = new stdClass();
        $input->_id = 123;
        $input->name = 'John';

        $result = $this->grammar->prepareFieldsForResult($input);

        $this->assertInstanceOf(stdClass::class, $result);
        $this->assertTrue(property_exists($result, 'id'));
        $this->assertFalse(property_exists($result, '_id'));
        $this->assertEquals(123, $result->id);
        $this->assertEquals('John', $result->name);
    }

    /**
     * Test that nested stdClass objects are processed recursively
     */
    public function testPrepareFieldsForResultProcessesNestedStdClass()
    {
        $nested = new stdClass();
        $nested->_id = 456;
        $nested->email = 'john@example.com';

        $input = new stdClass();
        $input->_id = 123;
        $input->user = $nested;

        $result = $this->grammar->prepareFieldsForResult($input);

        $this->assertEquals(123, $result->id);
        $this->assertTrue(property_exists($result->user, 'id'));
        $this->assertEquals(456, $result->user->id);
        $this->assertEquals('john@example.com', $result->user->email);
    }

    /**
     * Test that nested stdClass objects respect renameEmbeddedIdField configuration
     */
    public function testPrepareFieldsForResultProcessesNestedStdClassWithConfigDisabled()
    {
        $this->connection->setRenameEmbeddedIdField(false);

        $nested = new stdClass();
        $nested->_id = 456;
        $nested->email = 'john@example.com';

        $input = new stdClass();
        $input->_id = 123;
        $input->user = $nested;

        $result = $this->grammar->prepareFieldsForResult($input);

        // Root level _id should still be converted to id
        $this->assertEquals(123, $result->id);
        // But nested _id should NOT be converted
        $this->assertTrue(property_exists($result->user, '_id'));
        $this->assertFalse(property_exists($result->user, 'id'));
        $this->assertEquals(456, $result->user->_id);
        $this->assertEquals('john@example.com', $result->user->email);

        // Reset for other tests
        $this->connection->setRenameEmbeddedIdField(true);
    }

    /**
     * Test that mixed nested arrays and objects are handled correctly
     */
    public function testPrepareFieldsForResultHandlesMixedNestedStructures()
    {
        $object = new stdClass();
        $object->_id = 789;
        $object->type = 'nested';

        $input = [
            '_id' => 123,
            'nested' => [
                '_id' => 456,
                'object' => $object,
            ],
        ];

        $result = $this->grammar->prepareFieldsForResult($input);

        $this->assertEquals(123, $result['id']);
        $this->assertEquals(456, $result['nested']['id']);
        $this->assertEquals(789, $result['nested']['object']->id);
        $this->assertEquals('nested', $result['nested']['object']->type);
    }

    /**
     * Test that having both id and _id in result does not convert
     */
    public function testPrepareFieldsForResultDoesNotConvertWhenIdAlreadyExists()
    {
        $input = ['_id' => 123, 'id' => 456, 'name' => 'John'];
        $result = $this->grammar->prepareFieldsForResult($input);

        // Should keep both as-is when id already exists
        $this->assertArrayHasKey('_id', $result);
        $this->assertArrayHasKey('id', $result);
        $this->assertEquals(123, $result['_id']);
        $this->assertEquals(456, $result['id']);
    }

    /**
     * Helper method to get a MongoDB connection instance
     */
    protected function getMongoConnection(): Connection
    {
        return $this->app['db']->connection('mongodb');
    }
}
