<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use MongoDB\Laravel\Connection;
use Throwable;

use function array_key_first;
use function array_merge;
use function array_walk;
use function count;
use function implode;
use function in_array;
use function is_array;
use function sprintf;
use function str_replace;

/**
 * MongoDB equivalent of Boost's DatabaseQuery tool.
 *
 * Runs read-only MQL commands against a MongoDB connection. Boost's own
 * DatabaseQuery tool only understands SQL and will not run against a
 * "mongodb" driver connection.
 */
#[IsReadOnly]
class DatabaseQuery extends Tool
{
    // BSON objects are capped at 100 nesting levels:
    // https://www.mongodb.com/docs/manual/reference/limits/#mongodb-limit-Nested-Depth-for-BSON-Documents
    private const MAX_NESTING_LEVEL = 100;

    // Aggregation pipelines are capped at 1000 stages
    // https://www.mongodb.com/docs/manual/core/aggregation-pipeline-limits/#number-of-stages-restrictions
    private const MAX_STAGES_PER_PIPELINE = 1000;

    /**
     * The tool's name.
     *
     * Set explicitly so it does not collide with Boost's own `database-query` tool.
     */
    protected string $name = 'database-query-mongodb';

    /**
     * The tool's description.
     */
    protected string $description = 'Execute a read-only MQL command against a MongoDB connection. Pass a "command" argument (an MQL command document, e.g. {"find": "users", "filter": {"active": true}}) — never SQL. Only read commands are allowed (aggregate, count, distinct, find). Only use this tool with a connection you already know uses the "mongodb" driver.';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'command' => $schema->object()
                ->description('The MQL command to execute. Only read commands are allowed (i.e. aggregate, count, distinct, find).')
                ->required(),
            'connection' => $schema->string()
                ->description('Name of the MongoDB database connection to query. Must be a connection that uses the "mongodb" driver.')
                ->required(),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $connectionName = (string) $request->get('connection');
        $connection = DB::connection($connectionName);

        if (! $connection instanceof Connection) {
            return Response::error(sprintf('The [%s] connection is not a MongoDB connection.', $connectionName));
        }

        try {
            return Response::json($this->handleMql($request->array('command'), $connection));
        } catch (InvalidArgumentException $exception) {
            return Response::error($exception->getMessage());
        } catch (Throwable $exception) {
            return Response::error('Query failed: ' . $exception->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $command
     *
     * @return array<int, mixed>
     *
     * @throws InvalidArgumentException
     */
    private function handleMql(array $command, Connection $connection): array
    {
        if ($command === []) {
            throw new InvalidArgumentException('Please pass a valid MongoDB command');
        }

        // Allowed CRUD commands (https://www.mongodb.com/docs/manual/reference/mql/crud-commands/)
        $allowList = ['aggregate', 'count', 'distinct', 'find'];
        $operation = array_key_first($command);

        if (! in_array($operation, $allowList, true)) {
            throw new InvalidArgumentException(sprintf('Only read commands are allowed (%s).', implode(', ', $allowList)));
        }

        if ($operation === 'aggregate') {
            // Check nested write ops recursively with conservative allow list
            $this->ensureNoNestedWriteInAggregation($command['pipeline'] ?? []);
        }

        return $connection->getDatabase()->command($command)->toArray();
    }

    /**
     * @param array<int, array<string, mixed>> $pipeline
     *
     * @throws InvalidArgumentException
     */
    private function ensureNoNestedWriteInAggregation(array $pipeline, int $level = 1): void
    {
        if ($level > self::MAX_NESTING_LEVEL) {
            throw new InvalidArgumentException(sprintf('Aggregation nesting exceeds the maximum of %d levels.', self::MAX_NESTING_LEVEL));
        }

        if (count($pipeline) > self::MAX_STAGES_PER_PIPELINE) {
            throw new InvalidArgumentException(sprintf('A pipeline exceeds the maximum of %d stages.', self::MAX_STAGES_PER_PIPELINE));
        }

        // These aggregation stages may contain nested aggregation pipelines
        // and must be checked for write ops recursively.
        $supportsNestedWrites = ['facet', 'lookup', 'unionWith'];

        // Every known read-only aggregation stage. Kept as an allow list (rather than
        // denying $merge/$out) so that write stages added in the future are rejected by
        // default. See https://www.mongodb.com/docs/manual/reference/mql/aggregation-stages
        $allowList = array_merge($supportsNestedWrites, [
            'addFields',
            'bucket',
            'bucketAuto',
            'changeStream',
            'changeStreamSplitLargeEvent',
            'collStats',
            'count',
            'currentOp',
            'densify',
            'documents',
            'fill',
            'geoNear',
            'graphLookup',
            'group',
            'indexStats',
            'limit',
            'listLocalSessions',
            'listSampledQueries',
            'listSearchIndexes',
            'listSessions',
            'match',
            'planCacheStats',
            'project',
            'redact',
            'replaceRoot',
            'replaceWith',
            'sample',
            'search',
            'searchMeta',
            'set',
            'setWindowFields',
            'shardedDataDistribution',
            'skip',
            'sort',
            'sortByCount',
            'unwind',
            'unset',
            'vectorSearch',
        ]);

        // A pipeline is a list of single-key stage documents, e.g. [['$match' => [...]], ...].
        foreach ($pipeline as $stage) {
            $operator = array_key_first($stage);
            $stageName = str_replace('$', '', (string) $operator);
            $stageBody = $stage[$operator];

            if (! in_array($stageName, $allowList, true)) {
                throw new InvalidArgumentException(sprintf('Only read aggregation stages are allowed. Found: %s', $stageName));
            }

            if (! in_array($stageName, $supportsNestedWrites, true)) {
                continue;
            }

            switch ($stageName) {
                case 'facet':
                    array_walk($stageBody, fn ($pipeline) => $this->ensureNoNestedWriteInAggregation($pipeline, $level + 1));

                    break;

                case 'lookup':
                case 'unionWith':
                    // Both stages take an optional single sub-pipeline; unionWith may also be a plain collection name.
                    if (is_array($stageBody) && isset($stageBody['pipeline'])) {
                        $this->ensureNoNestedWriteInAggregation($stageBody['pipeline'], $level + 1);
                    }

                    break;
            }
        }
    }
}
