<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Concerns;

use Closure;
use MongoDB\Client;
use MongoDB\Driver\Exception\RuntimeException;
use MongoDB\Driver\Session;
use MongoDB\Laravel\Connection;
use Throwable;

use function max;
use function MongoDB\with_transaction;
use function property_exists;

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
        $this->runCallbacksBeforeTransaction();

        $this->getSessionOrCreate()->startTransaction($options);

        $this->handleInitialTransactionState();
    }

    private function handleInitialTransactionState(): void
    {
        $this->transactions = 1;

        $this->transactionsManager?->begin(
            $this->getName(),
            $this->transactions,
        );

        $this->fireConnectionEvent('beganTransaction');
    }

    /**
     * Commit transaction in this session.
     */
    public function commit(): void
    {
        $this->fireConnectionEvent('committing');
        $this->getSessionOrThrow()->commitTransaction();

        $this->handleCommitState();
    }

    private function handleCommitState(): void
    {
        [$levelBeingCommitted, $this->transactions] = [
            $this->transactions,
            max(0, $this->transactions - 1),
        ];

        $this->transactionsManager?->commit(
            $this->getName(),
            $levelBeingCommitted,
            $this->transactions,
        );

        $this->fireConnectionEvent('committed');
    }

    /**
     * Abort transaction in this session.
     */
    public function rollBack($toLevel = null): void
    {
        $session = $this->getSessionOrThrow();
        if ($session->isInTransaction()) {
            $session->abortTransaction();
        }

        $this->handleRollbackState();
    }

    private function handleRollbackState(): void
    {
        $this->transactions = 0;

        $this->transactionsManager?->rollback(
            $this->getName(),
            $this->transactions,
        );

        $this->fireConnectionEvent('rollingBack');
    }

    private function runCallbacksBeforeTransaction(): void
    {
        // ToDo: remove conditional once we stop supporting Laravel 10.x
        if (property_exists(Connection::class, 'beforeStartingTransaction')) {
            foreach ($this->beforeStartingTransaction as $beforeTransactionCallback) {
                $beforeTransactionCallback($this);
            }
        }
    }

    /**
     * Static transaction function realize the with_transaction functionality provided by MongoDB.
     *
     * @param int $attempts
     *
     * @throws Throwable
     */
    public function transaction(Closure $callback, $attempts = 1, array $options = []): mixed
    {
        $attemptsLeft   = $attempts;
        $callbackResult = null;
        $throwable      = null;

        $callbackFunction = function (Session $session) use ($callback, &$attemptsLeft, &$callbackResult, &$throwable) {
            $attemptsLeft--;

            if ($attemptsLeft < 0) {
                $session->abortTransaction();
                $this->handleRollbackState();

                return;
            }

            $this->runCallbacksBeforeTransaction();
            $this->handleInitialTransactionState();

            // Catch, store, and re-throw any exception thrown during execution
            // of the callable. The last exception is re-thrown if the transaction
            // was aborted because the number of callback attempts has been exceeded.
            try {
                $callbackResult = $callback($this);
                $this->fireConnectionEvent('committing');
            } catch (Throwable $throwable) {
                throw $throwable;
            }
        };

        with_transaction($this->getSessionOrCreate(), $callbackFunction, $options);

        if ($attemptsLeft < 0 && $throwable) {
            $this->handleRollbackState();
            throw $throwable;
        }

        $this->handleCommitState();

        return $callbackResult;
    }
}
