<?php

namespace MongoDB\Laravel\Tests\Scout;

use ArrayIterator;
use Closure;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection as LaravelCollection;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\Jobs\RemoveFromSearch;
use LogicException;
use MongoDB\BSON\Document;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\Driver\CursorInterface;
use MongoDB\Laravel\Scout\ScoutEngine;
use MongoDB\Laravel\Tests\Scout\Models\ScoutUser;
use MongoDB\Laravel\Tests\Scout\Models\SearchableModel;
use MongoDB\Laravel\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use TypeError;

use function array_replace_recursive;
use function count;
use function serialize;
use function unserialize;

/** Unit tests that do not require an Atlas Search cluster */
class ScoutEngineTest extends TestCase
{
    private const EXPECTED_TYPEMAP = ['root' => 'object', 'document' => 'bson', 'array' => 'bson'];

    public function testCreateIndexInvalidDefinition(): void
    {
        $database = $this->createMock(Database::class);
        $engine = new ScoutEngine($database, false, ['collection_invalid' => ['foo' => 'bar']]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Invalid search index definition for collection "collection_invalid", the "mappings" key is required.');
        $engine->createIndex('collection_invalid');
    }

    public function testCreateIndex(): void
    {
        $collectionName = 'collection_custom';
        $expectedDefinition = [
            'mappings' => ['dynamic' => true],
        ];

        $database = $this->createMock(Database::class);
        $collection = $this->createMock(Collection::class);
        $database->expects($this->once())
            ->method('createCollection')
            ->with($collectionName);
        $database->expects($this->once())
            ->method('selectCollection')
            ->with($collectionName)
            ->willReturn($collection);
        $collection->expects($this->once())
            ->method('createSearchIndex')
            ->with($expectedDefinition, ['name' => 'scout']);
        $collection->expects($this->once())
            ->method('listSearchIndexes')
            ->with(['name' => 'scout', 'typeMap' => ['root' => 'bson']])
            ->willReturn(new ArrayIterator([Document::fromPHP(['name' => 'scout', 'status' => 'READY'])]));

        $engine = new ScoutEngine($database, false, []);
        $engine->createIndex($collectionName);
    }

    public function testCreateIndexCustomDefinition(): void
    {
        $collectionName = 'collection_custom';
        $expectedDefinition = [
            'mappings' => [
                [
                    'analyzer' => 'lucene.standard',
                    'fields' => [
                        [
                            'name' => 'wildcard',
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
        ];

        $database = $this->createMock(Database::class);
        $collection = $this->createMock(Collection::class);
        $database->expects($this->once())
            ->method('createCollection')
            ->with($collectionName);
        $database->expects($this->once())
            ->method('selectCollection')
            ->with($collectionName)
            ->willReturn($collection);
        $collection->expects($this->once())
            ->method('createSearchIndex')
            ->with($expectedDefinition, ['name' => 'scout']);
        $collection->expects($this->once())
            ->method('listSearchIndexes')
            ->with(['name' => 'scout', 'typeMap' => ['root' => 'bson']])
            ->willReturn(new ArrayIterator([Document::fromPHP(['name' => 'scout', 'status' => 'READY'])]));

        $engine = new ScoutEngine($database, false, [$collectionName => $expectedDefinition]);
        $engine->createIndex($collectionName);
    }

    /** @param callable(): Builder $builder  */
    #[DataProvider('provideSearchPipelines')]
    public function testSearch(Closure $builder, array $expectedPipeline): void
    {
        $data = [['_id' => 'key_1', '__count' => 15], ['_id' => 'key_2', '__count' => 15]];
        $database = $this->createMock(Database::class);
        $collection = $this->createMock(Collection::class);
        $database->expects($this->once())
            ->method('selectCollection')
            ->with('collection_searchable')
            ->willReturn($collection);
        $cursor = $this->createMock(CursorInterface::class);
        $cursor->expects($this->once())
            ->method('setTypeMap')
            ->with(self::EXPECTED_TYPEMAP);
        $cursor->expects($this->once())
            ->method('toArray')
            ->with()
            ->willReturn($data);

        $collection->expects($this->any())
            ->method('getCollectionName')
            ->willReturn('collection_searchable');
        $collection->expects($this->once())
            ->method('aggregate')
            ->with($expectedPipeline)
            ->willReturn($cursor);

        $engine = new ScoutEngine($database, softDelete: false);
        $result = $engine->search($builder());
        $this->assertEquals($data, $result);
    }

    public static function provideSearchPipelines(): iterable
    {
        $defaultPipeline = [
            [
                '$search' => [
                    'index' => 'scout',
                    'compound' => [
                        'should' => [
                            [
                                'text' => [
                                    'path' => ['wildcard' => '*'],
                                    'query' => 'lar',
                                    'fuzzy' => ['maxEdits' => 2],
                                    'score' => ['boost' => ['value' => 5]],
                                ],
                            ],
                            [
                                'wildcard' => [
                                    'query' => 'lar*',
                                    'path' => ['wildcard' => '*'],
                                    'allowAnalyzedField' => true,
                                ],
                            ],
                        ],
                        'minimumShouldMatch' => 1,
                    ],
                    'count' => ['type' => 'lowerBound'],
                ],
            ],
            [
                '$addFields' => ['__count' => '$$SEARCH_META.count.lowerBound'],
            ],
        ];

        yield 'simple string' => [
            function () {
                return new Builder(new SearchableModel(), 'lar');
            },
            $defaultPipeline,
        ];

        yield 'where conditions' => [
            function () {
                $builder = new Builder(new SearchableModel(), 'lar');
                $builder->where('foo', 'bar');
                $builder->where('key', 'value');

                return $builder;
            },
            array_replace_recursive($defaultPipeline, [
                [
                    '$search' => [
                        'compound' => [
                            'filter' => [
                                ['equals' => ['path' => 'foo', 'value' => 'bar']],
                                ['equals' => ['path' => 'key', 'value' => 'value']],
                            ],
                        ],
                    ],
                ],
            ]),
        ];

        yield 'where in conditions' => [
            function () {
                $builder = new Builder(new SearchableModel(), 'lar');
                $builder->where('foo', 'bar');
                $builder->where('bar', 'baz');
                $builder->whereIn('qux', [1, 2]);
                $builder->whereIn('quux', [1, 2]);

                return $builder;
            },
            array_replace_recursive($defaultPipeline, [
                [
                    '$search' => [
                        'compound' => [
                            'filter' => [
                                ['equals' => ['path' => 'foo', 'value' => 'bar']],
                                ['equals' => ['path' => 'bar', 'value' => 'baz']],
                                ['in' => ['path' => 'qux', 'value' => [1, 2]]],
                                ['in' => ['path' => 'quux', 'value' => [1, 2]]],
                            ],
                        ],
                    ],
                ],
            ]),
        ];

        yield 'where not in conditions' => [
            function () {
                $builder = new Builder(new SearchableModel(), 'lar');
                $builder->where('foo', 'bar');
                $builder->where('bar', 'baz');
                $builder->whereIn('qux', [1, 2]);
                $builder->whereIn('quux', [1, 2]);
                $builder->whereNotIn('eaea', [3]);

                return $builder;
            },
            array_replace_recursive($defaultPipeline, [
                [
                    '$search' => [
                        'compound' => [
                            'filter' => [
                                ['equals' => ['path' => 'foo', 'value' => 'bar']],
                                ['equals' => ['path' => 'bar', 'value' => 'baz']],
                                ['in' => ['path' => 'qux', 'value' => [1, 2]]],
                                ['in' => ['path' => 'quux', 'value' => [1, 2]]],
                            ],
                            'mustNot' => [
                                ['in' => ['path' => 'eaea', 'value' => [3]]],
                            ],
                        ],
                    ],
                ],
            ]),
        ];

        yield 'where in conditions without other conditions' => [
            function () {
                $builder = new Builder(new SearchableModel(), 'lar');
                $builder->whereIn('qux', [1, 2]);
                $builder->whereIn('quux', [1, 2]);

                return $builder;
            },
            array_replace_recursive($defaultPipeline, [
                [
                    '$search' => [
                        'compound' => [
                            'filter' => [
                                ['in' => ['path' => 'qux', 'value' => [1, 2]]],
                                ['in' => ['path' => 'quux', 'value' => [1, 2]]],
                            ],
                        ],
                    ],
                ],
            ]),
        ];

        yield 'where not in conditions without other conditions' => [
            function () {
                $builder = new Builder(new SearchableModel(), 'lar');
                $builder->whereIn('qux', [1, 2]);
                $builder->whereIn('quux', [1, 2]);
                $builder->whereNotIn('eaea', [3]);

                return $builder;
            },
            array_replace_recursive($defaultPipeline, [
                [
                    '$search' => [
                        'compound' => [
                            'filter' => [
                                ['in' => ['path' => 'qux', 'value' => [1, 2]]],
                                ['in' => ['path' => 'quux', 'value' => [1, 2]]],
                            ],
                            'mustNot' => [
                                ['in' => ['path' => 'eaea', 'value' => [3]]],
                            ],
                        ],
                    ],
                ],
            ]),
        ];

        yield 'empty where in conditions' => [
            function () {
                $builder = new Builder(new SearchableModel(), 'lar');
                $builder->whereIn('qux', [1, 2]);
                $builder->whereIn('quux', [1, 2]);
                $builder->whereNotIn('eaea', [3]);

                return $builder;
            },
            array_replace_recursive($defaultPipeline, [
                [
                    '$search' => [
                        'compound' => [
                            'filter' => [
                                ['in' => ['path' => 'qux', 'value' => [1, 2]]],
                                ['in' => ['path' => 'quux', 'value' => [1, 2]]],
                            ],
                            'mustNot' => [
                                ['in' => ['path' => 'eaea', 'value' => [3]]],
                            ],
                        ],
                    ],
                ],
            ]),
        ];

        yield 'exclude soft-deleted' => [
            function () {
                return new Builder(new SearchableModel(), 'lar', softDelete: true);
            },
            array_replace_recursive($defaultPipeline, [
                [
                    '$search' => [
                        'compound' => [
                            'filter' => [
                                ['equals' => ['path' => '__soft_deleted', 'value' => false]],
                            ],
                        ],
                    ],
                ],
            ]),
        ];

        yield 'only trashed' => [
            function () {
                $builder = new Builder(new SearchableModel(), 'lar', softDelete: true);
                $builder->onlyTrashed();

                return $builder;
            },
            array_replace_recursive($defaultPipeline, [
                [
                    '$search' => [
                        'compound' => [
                            'filter' => [
                                ['equals' => ['path' => '__soft_deleted', 'value' => true]],
                            ],
                        ],
                    ],
                ],
            ]),
        ];

        yield 'with callback' => [
            fn () => new Builder(new SearchableModel(), 'query', callback: function (...$args) {
                self::assertCount(3, $args);
                self::assertInstanceOf(Collection::class, $args[0]);
                self::assertSame('collection_searchable', $args[0]->getCollectionName());
                self::assertSame('query', $args[1]);
                self::assertNull($args[2]);

                return $args[0]->aggregate(['pipeline']);
            }),
            ['pipeline'],
        ];

        yield 'ordered' => [
            function () {
                $builder = new Builder(new SearchableModel(), 'lar');
                $builder->orderBy('name', 'desc');
                $builder->orderBy('age', 'asc');

                return $builder;
            },
            array_replace_recursive($defaultPipeline, [
                [
                    '$search' => [
                        'sort' => [
                            'name' => -1,
                            'age' => 1,
                        ],
                    ],
                ],
            ]),
        ];
    }

    public function testPaginate()
    {
        $perPage = 5;
        $page = 3;

        $database = $this->createMock(Database::class);
        $collection = $this->createMock(Collection::class);
        $cursor = $this->createMock(CursorInterface::class);
        $database->method('selectCollection')
            ->with('collection_searchable')
            ->willReturn($collection);
        $collection->expects($this->once())
            ->method('aggregate')
            ->willReturnCallback(function (...$args) use ($cursor) {
                self::assertSame([
                    [
                        '$search' => [
                            'index' => 'scout',
                            'compound' => [
                                'should' => [
                                    [
                                        'text' => [
                                            'query' => 'mustang',
                                            'path' => ['wildcard' => '*'],
                                            'fuzzy' => ['maxEdits' => 2],
                                            'score' => ['boost' => ['value' => 5]],
                                        ],
                                    ],
                                    [
                                        'wildcard' => [
                                            'query' => 'mustang*',
                                            'path' => ['wildcard' => '*'],
                                            'allowAnalyzedField' => true,
                                        ],
                                    ],
                                ],
                                'minimumShouldMatch' => 1,
                            ],
                            'count' => ['type' => 'lowerBound'],
                            'sort' => [
                                'name' => -1,
                            ],
                        ],
                    ],
                    [
                        '$addFields' => ['__count' => '$$SEARCH_META.count.lowerBound'],
                    ],
                    ['$skip' => 10],
                    ['$limit' => 5],
                ], $args[0]);

                return $cursor;
            });
        $cursor->expects($this->once())->method('setTypeMap')->with(self::EXPECTED_TYPEMAP);
        $cursor->expects($this->once())->method('toArray')->with()
            ->willReturn([['_id' => 'key_1', '__count' => 17], ['_id' => 'key_2', '__count' => 17]]);

        $engine = new ScoutEngine($database, softDelete: false);
        $builder = new Builder(new SearchableModel(), 'mustang');
        $builder->orderBy('name', 'desc');
        $engine->paginate($builder, $perPage, $page);
    }

    public function testMapMethodRespectsOrder()
    {
        $database = $this->createMock(Database::class);
        $query = $this->createMock(Builder::class);
        $engine = new ScoutEngine($database, false);

        $model = $this->createMock(SearchableModel::class);
        $model->expects($this->any())
            ->method('getScoutKeyName')
            ->willReturn('id');
        $model->expects($this->once())
            ->method('queryScoutModelsByIds')
            ->willReturn($query);
        $query->expects($this->once())
            ->method('get')
            ->willReturn(LaravelCollection::make([
                new ScoutUser(['id' => 1]),
                new ScoutUser(['id' => 2]),
                new ScoutUser(['id' => 3]),
                new ScoutUser(['id' => 4]),
            ]));

        $builder = $this->createMock(Builder::class);

        $results = $engine->map($builder, [
            ['_id' => 1, '__count' => 4],
            ['_id' => 2, '__count' => 4],
            ['_id' => 4, '__count' => 4],
            ['_id' => 3, '__count' => 4],
        ], $model);

        $this->assertEquals(4, count($results));
        $this->assertEquals([
            0 => ['id' => 1],
            1 => ['id' => 2],
            2 => ['id' => 4],
            3 => ['id' => 3],
        ], $results->toArray());
    }

    public function testLazyMapMethodRespectsOrder()
    {
        $database = $this->createMock(Database::class);
        $query = $this->createMock(Builder::class);
        $engine = new ScoutEngine($database, false);

        $model = $this->createMock(SearchableModel::class);
        $model->expects($this->any())
            ->method('getScoutKeyName')
            ->willReturn('id');
        $model->expects($this->once())
            ->method('queryScoutModelsByIds')
            ->willReturn($query);
        $query->expects($this->once())
            ->method('cursor')
            ->willReturn(LazyCollection::make([
                new ScoutUser(['id' => 1]),
                new ScoutUser(['id' => 2]),
                new ScoutUser(['id' => 3]),
                new ScoutUser(['id' => 4]),
            ]));

        $builder = $this->createMock(Builder::class);

        $results = $engine->lazyMap($builder, [
            ['_id' => 1, '__count' => 4],
            ['_id' => 2, '__count' => 4],
            ['_id' => 4, '__count' => 4],
            ['_id' => 3, '__count' => 4],
        ], $model);

        $this->assertEquals(4, count($results));
        $this->assertEquals([
            0 => ['id' => 1],
            1 => ['id' => 2],
            2 => ['id' => 4],
            3 => ['id' => 3],
        ], $results->toArray());
    }

    public function testUpdate(): void
    {
        $date = new DateTimeImmutable('2000-01-02 03:04:05');
        $database = $this->createMock(Database::class);
        $collection = $this->createMock(Collection::class);
        $database->expects($this->once())
            ->method('selectCollection')
            ->with('collection_indexable')
            ->willReturn($collection);
        $collection->expects($this->once())
            ->method('bulkWrite')
            ->with([
                [
                    'updateOne' => [
                        ['_id' => 'key_1'],
                        ['$set' => ['id' => 1, 'date' => new UTCDateTime($date)]],
                        ['upsert' => true],
                    ],
                ],
                [
                    'updateOne' => [
                        ['_id' => 'key_2'],
                        ['$set' => ['id' => 2]],
                        ['upsert' => true],
                    ],
                ],
            ]);

        $engine = new ScoutEngine($database, softDelete: false);
        $engine->update(EloquentCollection::make([
            new SearchableModel([
                'id' => 1,
                'date' => $date,
            ]),
            new SearchableModel(['id' => 2]),
        ]));
    }

    public function testUpdateWithSoftDelete(): void
    {
        $date = new DateTimeImmutable('2000-01-02 03:04:05');
        $database = $this->createMock(Database::class);
        $collection = $this->createMock(Collection::class);
        $database->expects($this->once())
            ->method('selectCollection')
            ->with('collection_indexable')
            ->willReturn($collection);
        $collection->expects($this->once())
            ->method('bulkWrite')
            ->with([
                [
                    'updateOne' => [
                        ['_id' => 'key_1'],
                        ['$set' => ['id' => 1, '__soft_deleted' => false]],
                        ['upsert' => true],
                    ],
                ],
            ]);

        $model = new SearchableModel(['id' => 1]);
        $model->delete();

        $engine = new ScoutEngine($database, softDelete: true);
        $engine->update(EloquentCollection::make([$model]));
    }

    public function testDelete(): void
    {
        $database = $this->createMock(Database::class);
        $collection = $this->createMock(Collection::class);
        $database->expects($this->once())
            ->method('selectCollection')
            ->with('collection_indexable')
            ->willReturn($collection);
        $collection->expects($this->once())
            ->method('deleteMany')
            ->with(['_id' => ['$in' => ['key_1', 'key_2']]]);

        $engine = new ScoutEngine($database, softDelete: false);
        $engine->delete(EloquentCollection::make([
            new SearchableModel(['id' => 1]),
            new SearchableModel(['id' => 2]),
        ]));
    }

    public function testDeleteWithRemoveableScoutCollection(): void
    {
        $job = new RemoveFromSearch(EloquentCollection::make([
            new SearchableModel(['id' => 5, 'scout_key' => 'key_5']),
        ]));

        $job = unserialize(serialize($job));

        $database = $this->createMock(Database::class);
        $collection = $this->createMock(Collection::class);
        $database->expects($this->once())
            ->method('selectCollection')
            ->with('collection_indexable')
            ->willReturn($collection);
        $collection->expects($this->once())
            ->method('deleteMany')
            ->with(['_id' => ['$in' => ['key_5']]]);

        $engine = new ScoutEngine($database, softDelete: false);
        $engine->delete($job->models);
    }

    public function testDeleteRejectsNonEloquentCollection(): void
    {
        $database = $this->createMock(Database::class);
        $engine = new ScoutEngine($database, softDelete: false);

        $this->expectException(TypeError::class);
        $this->expectExceptionMessage(
            'Argument #1 ($models) must be of type Illuminate\Database\Eloquent\Collection',
        );

        $engine->delete(LaravelCollection::make([1, 2, 3]));
    }
}
