<?php

namespace MongoDB\Laravel;

use MongoDB\BSON\Document;
use MongoDB\Driver\Monitoring\CommandFailedEvent;
use MongoDB\Driver\Monitoring\CommandStartedEvent;
use MongoDB\Driver\Monitoring\CommandSubscriber as CommandSubscriberInterface;
use MongoDB\Driver\Monitoring\CommandSucceededEvent;
use Override;

use function get_object_vars;
use function in_array;

/** @internal */
final class CommandSubscriber implements CommandSubscriberInterface
{
    /** @var array<string, CommandStartedEvent> */
    private array $commands = [];

    public function __construct(private Connection $connection)
    {
    }

    #[Override]
    public function commandStarted(CommandStartedEvent $event): void
    {
        $this->commands[$event->getOperationId()] = $event;
    }

    #[Override]
    public function commandFailed(CommandFailedEvent $event): void
    {
        $this->logQuery($event);
    }

    #[Override]
    public function commandSucceeded(CommandSucceededEvent $event): void
    {
        $this->logQuery($event);
    }

    private function logQuery(CommandSucceededEvent|CommandFailedEvent $event): void
    {
        $startedEvent = $this->commands[$event->getOperationId()];
        unset($this->commands[$event->getOperationId()]);

        $command = [];
        foreach (get_object_vars($startedEvent->getCommand()) as $key => $value) {
            if ($key[0] !== '$' && ! in_array($key, ['lsid', 'txnNumber'])) {
                $command[$key] = $value;
            }
        }

        $this->connection->logQuery(Document::fromPHP($command)->toCanonicalExtendedJSON(), [], $event->getDurationMicros() / 1000);
    }
}
