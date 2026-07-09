<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use MongoDB\Database;
use MongoDB\Laravel\Connection;
use MongoDB\Model\CollectionInfo;
use Throwable;

use function assert;
use function rescue;
use function sprintf;
use function str_contains;
use function strtolower;

/**
 * MongoDB equivalent of Boost's DatabaseSchema tool.
 */
#[IsReadOnly]
class DatabaseInfo extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'Read information about a MongoDB database. MongoDB does not use tables and columns, so this returns a summary of the collections. Use "summary" mode first (the default) to get an overview (collection names, types, and estimated document counts), then call again with "summary" set to false and a "filter" to get full details for specific collections (indexes and options). Only use this tool with a connection you already know uses the "mongodb" driver. Params: "connection" (required) - the name of a MongoDB database connection to inspect; "summary" (default true) - collection names, types, and estimated document counts only; "filter" - substring match on collection names.';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->boolean()
                ->description('Return only collection names, types, and estimated document counts. Use this first to understand the database, then request full details for specific collections using "filter". Defaults to false.'),
            'connection' => $schema->string()
                ->description('Name of the MongoDB database connection to inspect. Must be a connection that uses the "mongodb" driver.')
                ->required(),
            'filter' => $schema->string()
                ->description('Filter collections by name (substring match).'),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $summary = (bool) $request->get('summary', false);
        $connectionName = (string) $request->get('connection');
        $filter = (string) ($request->get('filter') ?? '');

        $connection = DB::connection($connectionName);

        if (! $connection instanceof Connection) {
            return Response::error(sprintf('The [%s] connection is not a MongoDB connection.', $connectionName));
        }

        $cacheKey = sprintf(
            'mongodb:mcp:database-info:%s:%s:%d',
            $connectionName,
            $filter,
            (int) $summary,
        );

        try {
            $info = rescue(
                fn (): array => Cache::remember($cacheKey, 20, fn (): array => $this->getDatabaseInfo($connection, $connectionName, $filter, $summary)),
                fn (): array => $this->getDatabaseInfo($connection, $connectionName, $filter, $summary),
                report: false,
            );
        } catch (Throwable $exception) {
            Log::error('Failed to read information for MongoDB connection: ' . $connectionName, [
                'error' => $exception->getMessage(),
            ]);

            return Response::error(sprintf('Failed to read information for the [%s] connection.', $connectionName));
        }

        return Response::json($info);
    }

    /** @return array{database: string, connection: string, collections: array<string, mixed>} */
    protected function getDatabaseInfo(Connection $connection, string $connectionName, string $filter, bool $summary): array
    {
        $database = $connection->getDatabase();

        $collections = [];

        foreach ($database->listCollections() as $collectionInfo) {
            assert($collectionInfo instanceof CollectionInfo);
            $name = $collectionInfo->getName();

            if ($filter !== '' && ! str_contains(strtolower($name), strtolower($filter))) {
                continue;
            }

            $collections[$name] = $summary
                ? $this->getCollectionSummary($database, $collectionInfo)
                : $this->getCollectionDetails($database, $collectionInfo);
        }

        return [
            'database' => $database->getDatabaseName(),
            'connection' => $connectionName,
            'collections' => $collections,
        ];
    }

    /** @return array{type: string, estimated_document_count: int} */
    protected function getCollectionSummary(Database $database, CollectionInfo $collectionInfo): array
    {
        return [
            'type' => $collectionInfo->getType(),
            'estimated_document_count' => $this->estimatedDocumentCount($database, $collectionInfo->getName()),
        ];
    }

    /** @return array<string, mixed> */
    protected function getCollectionDetails(Database $database, CollectionInfo $collectionInfo): array
    {
        $name = $collectionInfo->getName();

        try {
            return [
                'type' => $collectionInfo->getType(),
                'estimated_document_count' => $this->estimatedDocumentCount($database, $name),
                'options' => $collectionInfo->getOptions(),
                'indexes' => $this->getIndexes($database, $name),
            ];
        } catch (Throwable $exception) {
            Log::error('Failed to get details for MongoDB collection: ' . $name, [
                'error' => $exception->getMessage(),
            ]);

            return ['error' => sprintf('Failed to get details for collection [%s].', $name)];
        }
    }

    protected function estimatedDocumentCount(Database $database, string $collection): int
    {
        try {
            return $database->getCollection($collection)->estimatedDocumentCount();
        } catch (Throwable) {
            return 0;
        }
    }

    /** @return array<string, array{keys: array<string, mixed>, unique: bool}> */
    protected function getIndexes(Database $database, string $collection): array
    {
        $indexes = [];

        foreach ($database->getCollection($collection)->listIndexes() as $index) {
            $indexes[$index->getName()] = [
                'keys' => $index->getKey(),
                'unique' => $index->isUnique(),
            ];
        }

        return $indexes;
    }
}
