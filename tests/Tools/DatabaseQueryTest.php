<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Tools;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Iterator;
use Mockery;
use MongoDB\BSON\Int64;
use MongoDB\Database;
use MongoDB\Driver\CursorInterface;
use MongoDB\Driver\Server;
use MongoDB\Laravel\Connection;
use MongoDB\Laravel\Tests\TestCase;
use MongoDB\Laravel\Tools\DatabaseQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

use function current;
use function key;
use function next;
use function reset;
use function sprintf;

class DatabaseQueryTest extends TestCase
{
    use InteractsWithTools;

    public function testRunsSuccessfulFindCommand(): void
    {
        $this->fakeMongoConnection([['_id' => 1, 'name' => 'Taylor']]);

        $rows = $this->toolJson(new DatabaseQuery(), [
            'connection' => 'mongodb',
            'command' => ['find' => 'examples', 'filter' => ['name' => 'Taylor']],
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame('Taylor', $rows[0]['name']);
    }

    /** @param list<array<string, mixed>> $pipeline */
    #[DataProvider('readOnlyAggregations')]
    public function testRunsReadOnlyAggregation(array $pipeline): void
    {
        $this->fakeMongoConnection([['count' => 1]]);

        $response = $this->runTool(new DatabaseQuery(), [
            'connection' => 'mongodb',
            'command' => ['aggregate' => 'examples', 'pipeline' => $pipeline],
        ]);

        $this->assertToolHasNoError($response);
    }

    public static function readOnlyAggregations(): array
    {
        return [
            'flat stages' => [
                [
                    ['$match' => ['name' => 'Taylor']],
                    ['$sort' => ['name' => 1]],
                ],
            ],
            'nested read-only lookup' => [
                [
                    ['$lookup' => ['from' => 'others', 'pipeline' => [['$match' => ['ok' => true]]]]],
                ],
            ],
            'nested read-only unionWith' => [
                [
                    ['$unionWith' => ['coll' => 'others', 'pipeline' => [['$match' => ['ok' => true]]]]],
                ],
            ],
            'nested read-only facet' => [
                [
                    [
                        '$facet' => [
                            'a' => [['$match' => ['ok' => true]]],
                            'b' => [['$count' => 'total']],
                        ],
                    ],
                ],
            ],
            'deeply nested read-only pipelines' => [
                [
                    [
                        '$lookup' => [
                            'from' => 'others',
                            'pipeline' => [
                                ['$unionWith' => ['coll' => 'more', 'pipeline' => [['$match' => ['ok' => true]]]]],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function testErrorsWhenConnectionIsNotMongoDB(): void
    {
        DB::shouldReceive('connection')->andReturn(Mockery::mock(ConnectionInterface::class));

        $response = $this->runTool(new DatabaseQuery(), [
            'connection' => 'sqlite',
            'command' => ['find' => 'examples'],
        ]);

        $this->assertToolHasError($response);
        $this->assertToolTextContains($response, 'The [sqlite] connection is not a MongoDB connection.');
    }

    public function testRejectsEmptyCommand(): void
    {
        $this->fakeMongoConnection();

        $response = $this->runTool(new DatabaseQuery(), [
            'connection' => 'mongodb',
            'command' => [],
        ]);

        $this->assertToolHasError($response);
        $this->assertToolTextContains($response, 'Please pass a valid MongoDB command');
    }

    public function testRejectsWriteCommand(): void
    {
        $this->fakeMongoConnection();

        $response = $this->runTool(new DatabaseQuery(), [
            'connection' => 'mongodb',
            'command' => ['insert' => 'examples', 'documents' => [['name' => 'Otwell']]],
        ]);

        $this->assertToolHasError($response);
        $this->assertToolTextContains($response, 'Only read commands are allowed (aggregate, count, distinct, find).');
    }

    /** @param list<array<string, mixed>> $pipeline */
    #[DataProvider('writeStagesNestedInAggregations')]
    public function testRejectsWriteStageNestedInAggregation(array $pipeline, string $writeStage): void
    {
        $this->fakeMongoConnection();

        $response = $this->runTool(new DatabaseQuery(), [
            'connection' => 'mongodb',
            'command' => ['aggregate' => 'examples', 'pipeline' => $pipeline],
        ]);

        $this->assertToolHasError($response);
        $this->assertToolTextContains($response, sprintf('Only read aggregation stages are allowed. Found: %s', $writeStage));
    }

    public static function writeStagesNestedInAggregations(): array
    {
        return [
            'write nested in lookup' => [
                [
                    [
                        '$lookup' => [
                            'from' => 'others',
                            'pipeline' => [
                                ['$match' => ['name' => 'Taylor']],
                                ['$merge' => 'evil'],
                            ],
                        ],
                    ],
                ],
                'merge',
            ],
            'write nested in unionWith' => [
                [
                    [
                        '$unionWith' => [
                            'coll' => 'others',
                            'pipeline' => [
                                ['$out' => 'evil'],
                            ],
                        ],
                    ],
                ],
                'out',
            ],
            'write nested in facet' => [
                [
                    [
                        '$facet' => [
                            'a' => [['$match' => ['ok' => true]]],
                            'b' => [['$merge' => 'evil']],
                        ],
                    ],
                ],
                'merge',
            ],
            'write nested three levels deep' => [
                [
                    [
                        '$lookup' => [
                            'from' => 'a',
                            'pipeline' => [
                                [
                                    '$unionWith' => [
                                        'coll' => 'b',
                                        'pipeline' => [
                                            ['$out' => 'evil'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'out',
            ],
        ];
    }

    /** @param array<string, mixed> $command */
    #[DataProvider('jsExecutionOperatorsInFilters')]
    public function testRejectsJsExecutionOperatorInFilter(array $command, string $operator): void
    {
        $this->fakeMongoConnection();

        $response = $this->runTool(new DatabaseQuery(), [
            'connection' => 'mongodb',
            'command' => $command,
        ]);

        $this->assertToolHasError($response);
        $this->assertToolTextContains($response, sprintf('Server-side JavaScript operators are not allowed. Found: %s', $operator));
    }

    public static function jsExecutionOperatorsInFilters(): array
    {
        return [
            'where in find filter' => [
                ['find' => 'examples', 'filter' => ['$where' => 'this.name === "Taylor"']],
                '$where',
            ],
            'where in count query' => [
                ['count' => 'examples', 'query' => ['$where' => 'true']],
                '$where',
            ],
            'where in distinct query' => [
                ['distinct' => 'examples', 'key' => 'name', 'query' => ['$where' => 'true']],
                '$where',
            ],
            'function nested in find filter' => [
                ['find' => 'examples', 'filter' => ['$expr' => ['$function' => ['body' => 'return true;', 'args' => [], 'lang' => 'js']]]],
                '$function',
            ],
            'where nested in count query' => [
                ['count' => 'examples', 'query' => ['$and' => [['$where' => 'true']]]],
                '$where',
            ],
        ];
    }

    /** @param list<array<string, mixed>> $pipeline */
    #[DataProvider('jsExecutionOperatorsInAggregations')]
    public function testRejectsJsExecutionOperatorInAggregationStage(array $pipeline, string $operator): void
    {
        $this->fakeMongoConnection();

        $response = $this->runTool(new DatabaseQuery(), [
            'connection' => 'mongodb',
            'command' => ['aggregate' => 'examples', 'pipeline' => $pipeline],
        ]);

        $this->assertToolHasError($response);
        $this->assertToolTextContains($response, sprintf('Server-side JavaScript operators are not allowed. Found: %s', $operator));
    }

    public static function jsExecutionOperatorsInAggregations(): array
    {
        return [
            'function in match' => [
                [
                    ['$match' => ['$expr' => ['$function' => ['body' => 'return true;', 'args' => [], 'lang' => 'js']]]],
                ],
                '$function',
            ],
            'accumulator in group' => [
                [
                    ['$group' => ['_id' => null, 'total' => ['$accumulator' => ['init' => 'function() { return 0; }', 'lang' => 'js']]]],
                ],
                '$accumulator',
            ],
            'where in project' => [
                [
                    ['$project' => ['matches' => ['$where' => 'true']]],
                ],
                '$where',
            ],
            'function nested in lookup pipeline' => [
                [
                    ['$lookup' => ['from' => 'others', 'pipeline' => [['$match' => ['$expr' => ['$function' => ['body' => 'return true;', 'args' => [], 'lang' => 'js']]]]]]],
                ],
                '$function',
            ],
            'accumulator nested in facet' => [
                [
                    ['$facet' => ['totals' => [['$group' => ['_id' => null, 'total' => ['$accumulator' => ['init' => 'function() { return 0; }', 'lang' => 'js']]]]]]],
                ],
                '$accumulator',
            ],
        ];
    }

    /** @param array<string, mixed> $command */
    #[DataProvider('jsExecutionOperatorsInCommandFields')]
    public function testRejectsJsExecutionOperatorInCommandField(array $command, string $operator): void
    {
        $this->fakeMongoConnection();

        $response = $this->runTool(new DatabaseQuery(), [
            'connection' => 'mongodb',
            'command' => $command,
        ]);

        $this->assertToolHasError($response);
        $this->assertToolTextContains($response, sprintf('Server-side JavaScript operators are not allowed. Found: %s', $operator));
    }

    public static function jsExecutionOperatorsInCommandFields(): array
    {
        return [
            'function in find projection' => [
                ['find' => 'examples', 'projection' => ['result' => ['$function' => ['body' => 'return 1;', 'args' => [], 'lang' => 'js']]]],
                '$function',
            ],
            'function in find let' => [
                ['find' => 'examples', 'let' => ['value' => ['$function' => ['body' => 'return 1;', 'args' => [], 'lang' => 'js']]]],
                '$function',
            ],
            'function in aggregate let' => [
                ['aggregate' => 'examples', 'pipeline' => [], 'let' => ['value' => ['$function' => ['body' => 'return 1;', 'args' => [], 'lang' => 'js']]]],
                '$function',
            ],
        ];
    }

    public function testReportsFailureWhenTheDriverThrows(): void
    {
        $database = Mockery::mock(Database::class);
        $database->shouldReceive('command')->andThrow(new RuntimeException('Simulated DB failure'));

        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDatabase')->andReturn($database);

        DB::shouldReceive('connection')->andReturn($connection);

        $response = $this->runTool(new DatabaseQuery(), [
            'connection' => 'mongodb',
            'command' => ['find' => 'examples'],
        ]);

        $this->assertToolHasError($response);
        $this->assertToolTextContains($response, 'Query failed: Simulated DB failure');
    }

    /**
     * Bind a fully mocked MongoDB connection so the tool never touches a real server.
     *
     * @param list<array<string, mixed>> $documents
     */
    private function fakeMongoConnection(array $documents = []): void
    {
        // Database::command() is typed to return a CursorInterface (Mockery enforces the return type),
        // so the double implements it. Iterator is listed explicitly because in ext-mongodb 1.x
        // CursorInterface only extends Traversable (in 2.x it extends Iterator); without it the class is
        // not a valid Traversable and PHP fatals at declaration time.
        $cursor = new class ($documents) implements CursorInterface, Iterator {
            /** @param list<array<string, mixed>> $documents */
            public function __construct(private array $documents)
            {
            }

            /** @return list<array<string, mixed>> */
            public function toArray(): array
            {
                return $this->documents;
            }

            public function current(): array|object|null
            {
                return current($this->documents) ?: null;
            }

            public function key(): ?int
            {
                return key($this->documents);
            }

            public function next(): void
            {
                next($this->documents);
            }

            public function valid(): bool
            {
                return key($this->documents) !== null;
            }

            public function rewind(): void
            {
                reset($this->documents);
            }

            public function getId(): Int64
            {
                throw new RuntimeException('unused in tests');
            }

            public function getServer(): Server
            {
                throw new RuntimeException('unused in tests');
            }

            public function isDead(): bool
            {
                return true;
            }

            public function setTypeMap(array $typemap): void
            {
            }
        };

        $database = Mockery::mock(Database::class);
        $database->shouldReceive('command')->andReturn($cursor);

        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDatabase')->andReturn($database);

        DB::shouldReceive('connection')->andReturn($connection);
    }
}
