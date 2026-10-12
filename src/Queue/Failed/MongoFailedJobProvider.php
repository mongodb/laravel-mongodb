<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Queue\Failed;

use Illuminate\Queue\Failed\DatabaseFailedJobProvider;
use MongoDB\BSON\ObjectId;
use Override;
use UnexpectedValueException;

use function array_map;
use function get_debug_type;
use function is_int;
use function is_string;
use function sprintf;

/**
 * Failed-job storage for a MongoDB connection.
 *
 * Queries go through {@see DatabaseFailedJobProvider}. `ids()` is the
 * exception: the query builder aliases `id` to `_id` but still returns
 * {@see ObjectId} instances, and `queue:retry` uses those values as array keys.
 */
class MongoFailedJobProvider extends DatabaseFailedJobProvider
{
    /**
     * Get the IDs of all of the failed jobs.
     *
     * @param string|null $queue
     *
     * @return list<int|string>
     */
    #[Override]
    public function ids($queue = null)
    {
        return array_map(self::stringifyId(...), parent::ids($queue));
    }

    private static function stringifyId(mixed $id): int|string
    {
        if ($id instanceof ObjectId) {
            return (string) $id;
        }

        if (is_int($id) || is_string($id)) {
            return $id;
        }

        throw new UnexpectedValueException(sprintf(
            'Failed job id must be a string, int, or ObjectId, %s given.',
            get_debug_type($id),
        ));
    }
}
