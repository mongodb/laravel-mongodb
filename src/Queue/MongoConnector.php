<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Queue;

use Illuminate\Contracts\Queue\Queue;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Queue\Connectors\ConnectorInterface;

class MongoConnector implements ConnectorInterface
{
    /**
     * Database connections.
     *
     * @var ConnectionResolverInterface
     */
    protected $connections;

    /**
     * Create a new connector instance.
     */
    public function __construct(ConnectionResolverInterface $connections)
    {
        $this->connections = $connections;
    }

    /**
     * Establish a queue connection.
     *
     * @return Queue
     */
    public function connect(array $config)
    {
        return new MongoQueue(
            $this->connections->connection($config['connection'] ?? null),
            $config['table'],
            $config['queue'],
            $config['expire'] ?? 60,
        );
    }
}
