<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Concerns;

use Closure;
use MongoDB\Client;
use MongoDB\Driver\Exception\RuntimeException;
use MongoDB\Driver\Session;
use Throwable;

use function MongoDB\with_transaction;

/**
 * @internal
 *
 * @see https://docs.mongodb.com/manual/core/transactions/
 */
trait ManagesTransactions
{
    protected ?Session $session = null;

    protected $transactions = 0;

    abstract public function getClient(): ?Client;

    public function getSession(): ?Session
    {
        return $this->session;
    }

    private function getSessionOrCreate(): Session
    {
        if ($this->session === null) {
            $this->session = $this->getClient()->startSession();
        }

        return $this->session;
    }

    private function getSessionOrThrow(): Session
    {
        $session = $this->getSession();

        if ($session === null) {
            throw new RuntimeException('There is no active session.');
        }

        return $session;
    }

    /**
     * Starts a transaction on the active session. An active session will be created if none exists.
     */
    public function beginTransaction(array $options = []): void
    {
        $this->getSessionOrCreate()->startTransaction($options);
        $this->transactions = 1;
    }

    /**
     * Commit transaction in this session.
     */
    public function commit(): void
    {
        $this->getSessionOrThrow()->commitTransaction();
        $this->transactions = 0;
    }

    /**
     * Abort transaction in this session.
     */
    public function rollBack($toLevel = null): void
    {
        $this->getSessionOrThrow()->abortTransaction();
        $this->transactions = 0;
    }

    /**
     * @param Closure $callback
     * @param $attempts
     * @param Closure|null $onFailure
     * @return mixed
     * @throws Throwable
     */
    public function transaction(Closure $callback, $attempts = 1, ?Closure $onFailure = null): mixed
    {

        if ($attempts <= 0) {
            throw new \InvalidArgumentException('Attempts must be at least 1');
        }

        $attemptsLeft = $attempts;
        $lastException = null;

        while ($attemptsLeft--) {
            $this->session = $this->getMongoClient()->startSession();

            try {
                $this->session->startTransaction();
                $result = $callback();
                $this->session->commitTransaction();

                return $result;
            } catch (\Throwable $e) {
                if ($this->session->isInTransaction()) {
                    $this->session->abortTransaction();
                }

                $lastException = $e;

                if ($e instanceof RuntimeException && $attemptsLeft > 0) {
                    continue;
                }

                if ($onFailure) {
                    return $onFailure($e);
                }

                throw $e;
            } finally {
                $this->session->endSession();
                $this->session = null;
            }
        }

        if ($onFailure) {
            return $onFailure($lastException);
        }

        throw $lastException;
    }
}
