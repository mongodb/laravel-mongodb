<?php

namespace Jenssegers\Mongodb\Concerns;

use Closure;
use MongoDB\Client;
use MongoDB\Driver\Exception\RuntimeException;
use MongoDB\Driver\Session;
use function MongoDB\with_transaction;
use Throwable;

/**
 * @see https://docs.mongodb.com/manual/core/transactions/
 */
trait ManagesTransactions
{
    protected $session = null;

    protected $transactions = 0;

    /**
     * @return Client
     */
    abstract public function getMongoClient();

    public function getSession()
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

    public function beginTransaction(array $options = [])
    {
        $this->getSessionOrCreate()->startTransaction($options);
        $this->transactions = 1;
    }

    public function commit()
    {
        $this->getSessionOrThrow()->commitTransaction();
        $this->transactions = 0;
    }

    public function rollBack($toLevel = null)
    {
        $this->getSessionOrThrow()->abortTransaction();
        $this->transactions = 0;
    }

    public function transaction(Closure $callback, $attempts = 1, array $options = [])
    {
        $attemptsLeft = $attempts;
        $callbackResult = null;
        $throwable = null;

        $callbackFunction = function (Session $session) use ($callback, &$attemptsLeft, &$callbackResult, &$throwable) {
            $attemptsLeft--;

            if ($attemptsLeft < 0) {
                $session->abortTransaction();

                return;
            }

            // Catch, store, and re-throw any exception thrown during execution
            // of the callable. The last exception is re-thrown if the transaction
            // was aborted because the number of callback attempts has been exceeded.
            try {
                $callbackResult = $callback($this);
            } catch (Throwable $throwable) {
                throw $throwable;
            }
        };

        with_transaction($this->getSessionOrCreate(), $callbackFunction, $options);

        if ($attemptsLeft < 0 && $throwable) {
            throw $throwable;
        }

        return $callbackResult;
    }
}
