<?php

namespace Jenssegers\Mongodb\Concerns;

use Closure;
use MongoDB\Client;
use MongoDB\Driver\Exception\RuntimeException;
use MongoDB\Driver\Session;
use function MongoDB\with_transaction;

/**
 * @see https://docs.mongodb.com/manual/core/transactions/
 */
trait ManagesTransactions
{
    protected ?Session $session = null;

    protected $transactions = 0;

    /**
     * @return Client
     */
    abstract public function getMongoClient();

    public function getSession(): ?Session
    {
        return $this->session;
    }

    private function getSessionOrCreate(): Session
    {
        if ($this->session === null) {
            $this->session = $this->getMongoClient()->startSession();
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
     * Static transaction function realize the with_transaction functionality provided by MongoDB.
     *
     * @param  int  $attempts
     */
    public function transaction(Closure $callback, $attempts = 1, array $options = []): mixed
    {
        $attemptsLeft = $attempts;
        $callbackResult = null;

        $callbackFunction = function (Session $session) use ($callback, &$attemptsLeft, &$callbackResult) {
            $attemptsLeft--;

            if ($attemptsLeft < 0) {
                $session->abortTransaction();

                return;
            }

            $callbackResult = $callback($this);
        };

        with_transaction($this->getSessionOrCreate(), $callbackFunction, $options);

        return $callbackResult;
    }
}
