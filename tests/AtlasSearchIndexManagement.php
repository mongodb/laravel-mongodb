<?php

namespace MongoDB\Laravel\Tests;

use MongoDB\Collection;
use RuntimeException;

use function hrtime;
use function usleep;

/**
 * Helpers for managing Atlas Search indexes in tests with awaiting mechanism.
 */
trait AtlasSearchIndexManagement
{
    /**
     * Waits for the search index created in the previous test to be deleted
     */
    public function waitForSearchIndexesDropped(Collection $collection)
    {
        $timeout = hrtime()[0] + 120;
        // Waits for the search index created in the previous test to be deleted
        while ($collection->listSearchIndexes()->count()) {
            if (hrtime()[0] > $timeout) {
                throw new RuntimeException('Timed out waiting for search indexes to be dropped');
            }

            usleep(1000);
        }
    }

    /**
     * Waits for a single named search index to be deleted.
     *
     * Unlike waitForSearchIndexesDropped(), this does not require the collection
     * to be empty of all indexes, so it can be used to clean up one extra index
     * while the shared fixture indexes remain in place.
     */
    public function waitForSearchIndexDropped(Collection $collection, string $name, int $timeoutSeconds = 120)
    {
        $timeout = hrtime()[0] + $timeoutSeconds;
        while ($this->hasSearchIndex($collection, $name)) {
            if (hrtime()[0] > $timeout) {
                throw new RuntimeException('Timed out waiting for search index "' . $name . '" to be dropped');
            }

            usleep(1000);
        }
    }

    private function hasSearchIndex(Collection $collection, string $name): bool
    {
        foreach ($collection->listSearchIndexes(['name' => $name]) as $index) {
            if (($index['name'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Waits for all search indexes to be ready
     */
    public function waitForSearchIndexesReady(Collection $collection, int $timeoutSeconds = 120)
    {
        $timeout = hrtime()[0] + $timeoutSeconds;
        do {
            if (hrtime()[0] > $timeout) {
                throw new RuntimeException('Timed out waiting for search indexes to be ready');
            }

            usleep(1000);
            $ready = true;
            foreach ($collection->listSearchIndexes() as $index) {
                $ready = $ready && $index['queryable'];
            }
        } while (! $ready);
    }
}
