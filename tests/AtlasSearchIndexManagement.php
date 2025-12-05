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
        $timeout = hrtime()[0] + 30;
        // Waits for the search index created in the previous test to be deleted
        while ($collection->listSearchIndexes()->count()) {
            if (hrtime()[0] > $timeout) {
                throw new RuntimeException('Timed out waiting for search indexes to be dropped');
            }

            usleep(1000);
        }
    }

    /**
     * Waits for all search indexes to be ready
     */
    public function waitForSearchIndexesReady(Collection $collection)
    {
        $timeout = hrtime()[0] + 30;
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
