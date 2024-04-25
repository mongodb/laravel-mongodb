<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Internal;

use MongoDB\Driver\Monitoring\CommandFailedEvent;
use MongoDB\Driver\Monitoring\CommandStartedEvent;
use MongoDB\Driver\Monitoring\CommandSubscriber;
use MongoDB\Driver\Monitoring\CommandSucceededEvent;

/**
 * Track findAndModify command events to detect when a document is inserted or
 * updated.
 *
 * @internal
 */
final class FindAndModifyCommandSubscriber implements CommandSubscriber
{
    public bool $created;

    public function commandFailed(CommandFailedEvent $event): void
    {
    }

    public function commandStarted(CommandStartedEvent $event): void
    {
    }

    public function commandSucceeded(CommandSucceededEvent $event): void
    {
        $this->created = ! $event->getReply()->lastErrorObject->updatedExisting;
    }
}
