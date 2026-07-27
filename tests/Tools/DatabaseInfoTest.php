<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Tools;

use ArrayIterator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\Laravel\Connection;
use MongoDB\Laravel\Tests\TestCase;
use MongoDB\Laravel\Tools\DatabaseInfo;
use MongoDB\Model\CollectionInfo;
use MongoDB\Model\IndexInfo;
use RuntimeException;

class DatabaseInfoTest extends TestCase
{
    use InteractsWithTools;

    public function testSummaryListsCollectionsWithCounts(): void
    {
        $this->fakeMongoConnection(
            collections: [new CollectionInfo(['name' => 'books', 'type' => 'collection'])],
            counts: ['books' => 3],
        );

        $info = $this->toolJson(new DatabaseInfo(), ['connection' => 'mongodb', 'summary' => true]);

        $this->assertSame('unittest', $info['database']);
        $this->assertSame('mongodb', $info['connection']);

        $collection = $info['collections']['books'];
        $this->assertSame('collection', $collection['type']);
        $this->assertSame(3, $collection['estimated_document_count']);
        $this->assertArrayNotHasKey('indexes', $collection);
    }

    public function testFilterExcludesNonMatchingCollections(): void
    {
        $this->fakeMongoConnection(
            collections: [
                new CollectionInfo(['name' => 'books', 'type' => 'collection']),
                new CollectionInfo(['name' => 'authors', 'type' => 'collection']),
            ],
            counts: ['books' => 1, 'authors' => 1],
        );

        $info = $this->toolJson(new DatabaseInfo(), ['filter' => 'book']);

        $this->assertArrayHasKey('books', $info['collections']);
        $this->assertArrayNotHasKey('authors', $info['collections']);
    }

    public function testFullDetailsIncludeIndexesAndOptions(): void
    {
        $this->fakeMongoConnection(
            collections: [new CollectionInfo(['name' => 'books', 'type' => 'collection', 'options' => ['capped' => true]])],
            counts: ['books' => 1],
            indexes: ['books' => [new IndexInfo(['name' => '_id_', 'key' => ['_id' => 1], 'v' => 2])]],
        );

        $info = $this->toolJson(new DatabaseInfo());

        $collection = $info['collections']['books'];
        $this->assertSame('collection', $collection['type']);
        $this->assertSame(1, $collection['estimated_document_count']);
        $this->assertSame(['capped' => true], $collection['options']);
        $this->assertSame(['_id' => 1], $collection['indexes']['_id_']['keys']);
        $this->assertFalse($collection['indexes']['_id_']['unique']);
    }

    public function testErrorsWhenConnectionIsNotMongoDB(): void
    {
        DB::shouldReceive('connection')->andReturn(Mockery::mock(ConnectionInterface::class));

        $response = $this->runTool(new DatabaseInfo(), ['connection' => 'sqlite']);

        $this->assertToolHasError($response);
        $this->assertToolTextContains($response, 'The [sqlite] connection is not a MongoDB connection.');
    }

    public function testReturnsErrorWhenListingCollectionsFails(): void
    {
        $database = Mockery::mock(Database::class);
        $database->shouldReceive('listCollections')->andThrow(new RuntimeException('super sekrit server error'));

        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDatabase')->andReturn($database);

        DB::shouldReceive('connection')->andReturn($connection);

        $response = $this->runTool(new DatabaseInfo(), ['connection' => 'mongodb']);

        $this->assertToolHasError($response);
        $this->assertToolTextContains($response, 'Failed to read information for the [mongodb] connection.');
        // The underlying driver message must not leak to the caller.
        $this->assertStringNotContainsString('super sekrit', (string) $response->content());
    }

    /**
     * Bind a fully mocked MongoDB connection so the tool never touches a real server.
     *
     * @param list<CollectionInfo>           $collections
     * @param array<string, int>             $counts      collection name => estimated document count
     * @param array<string, list<IndexInfo>> $indexes     collection name => indexes
     */
    private function fakeMongoConnection(array $collections, array $counts, array $indexes = []): void
    {
        $database = Mockery::mock(Database::class);
        $database->shouldReceive('getDatabaseName')->andReturn('unittest');
        $database->shouldReceive('listCollections')->andReturn(new ArrayIterator($collections));

        $database->shouldReceive('getCollection')->andReturnUsing(
            function (string $name) use ($counts, $indexes): MockInterface {
                $collection = Mockery::mock(Collection::class);
                $collection->shouldReceive('estimatedDocumentCount')->andReturn($counts[$name] ?? 0);
                $collection->shouldReceive('listIndexes')->andReturn(new ArrayIterator($indexes[$name] ?? []));

                return $collection;
            },
        );

        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDatabase')->andReturn($database);

        DB::shouldReceive('connection')->andReturn($connection);
    }
}
