<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Queue;

use Illuminate\Contracts\Queue\Queue;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Queue\Connectors\ConnectorInterface;

use function trigger_error;

use const E_USER_DEPRECATED;

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
        if (! isset($config['collection']) && isset($config['table'])) {
            trigger_error('Since mongodb/laravel-mongodb 4.4: Using "table" option in queue configuration is deprecated. Use "collection" instead.', E_USER_DEPRECATED);
            $config['collection'] = $config['table'];
        }

        if (! isset($config['retry_after']) && isset($config['expire'])) {
            trigger_error('Since mongodb/laravel-mongodb 4.4: Using "expire" option in queue configuration is deprecated. Use "retry_after" instead.', E_USER_DEPRECATED);
            $config['retry_after'] = $config['expire'];
        }

        return new MongoQueue(
            $this->connections->connection($config['connection'] ?? null),
            $config['collection'] ?? 'jobs',
            $config['queue'] ?? 'default',
            $config['retry_after'] ?? 60,
        );
    }
}
