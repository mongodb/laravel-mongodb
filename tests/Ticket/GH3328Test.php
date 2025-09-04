<?php

namespace MongoDB\Laravel\Tests\Ticket;

use Closure;
use Exception;
use Illuminate\Contracts\Database\ConcurrencyErrorDetector;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Concerns\ManagesTransactions;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionCommitting;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use MongoDB\Driver\Exception\RuntimeException;
use MongoDB\Laravel\Tests\TestCase;
use Throwable;

use function event;
use function interface_exists;
use function property_exists;

/**
 * @see https://github.com/mongodb/laravel-mongodb/issues/3328
 * @see https://jira.mongodb.org/browse/PHPORM-373
 */
class GH3328Test extends TestCase
{
    public function testAfterCommitOnSuccessfulTransaction(): void
    {
        $callback = static function (): void {
            event(new RegularEvent());
            event(new AfterCommitEvent());
        };

        $assert = function (): void {
            if ($this->beforeStartingTransactionIsSupported()) {
                Event::assertDispatchedTimes(BeforeTransactionEvent::class);
            }

            Event::assertDispatchedTimes(RegularEvent::class);
            Event::assertDispatchedTimes(AfterCommitEvent::class);

            Event::assertDispatched(TransactionBeginning::class);
            Event::assertDispatched(TransactionCommitting::class);
            Event::assertDispatched(TransactionCommitted::class);
        };

        $this->assertTransactionCallbackResult($callback, $assert);
    }

    public function testAfterCommitOnFailedTransaction(): void
    {
        $callback = static function (): void {
            event(new RegularEvent());
            event(new AfterCommitEvent());

            // Transaction failed; after commit event should not be dispatched
            throw new Fake();
        };

        $assert = function (): void {
            if ($this->beforeStartingTransactionIsSupported()) {
                Event::assertDispatchedTimes(BeforeTransactionEvent::class, 3);
            }

            Event::assertDispatchedTimes(RegularEvent::class, 3);

            Event::assertDispatchedTimes(TransactionBeginning::class, 3);
            Event::assertDispatched(TransactionRolledBack::class);
            Event::assertNotDispatched(TransactionCommitting::class);
            Event::assertNotDispatched(TransactionCommitted::class);
        };

        $this->assertCallbackResultForConnection(
            DB::connection('mongodb'),
            $callback,
            $assert,
            3,
        );

        if (! interface_exists(ConcurrencyErrorDetector::class)) {
            // Earlier versions of Laravel use a trait instead of DI to detect concurrency errors
            // That would increase the scope of this comparison dramatically and is probably not worth it.
            return;
        }

        $this->app->bind(ConcurrencyErrorDetector::class, FakeConcurrencyErrorDetector::class);

        $this->assertCallbackResultForConnection(
            DB::connection('sqlite'),
            $callback,
            $assert,
            3,
        );
    }

    public function testAfterCommitOnSuccessfulManualTransaction(): void
    {
        $callback = function (): void {
            event(new RegularEvent());
            event(new AfterCommitEvent());
        };

        $assert = function (): void {
            if ($this->beforeStartingTransactionIsSupported()) {
                Event::assertDispatchedTimes(BeforeTransactionEvent::class);
            }

            Event::assertDispatchedTimes(RegularEvent::class);
            Event::assertDispatchedTimes(AfterCommitEvent::class);

            Event::assertDispatched(TransactionBeginning::class);
            Event::assertNotDispatched(TransactionRolledBack::class);
            Event::assertDispatched(TransactionCommitting::class);
            Event::assertDispatched(TransactionCommitted::class);
        };

        $this->assertTransactionResult($callback, $assert);
    }

    public function testAfterCommitOnFailedManualTransaction(): void
    {
        $callback = function (): void {
            event(new RegularEvent());
            event(new AfterCommitEvent());

            throw new Fake();
        };

        $assert = function (): void {
            if ($this->beforeStartingTransactionIsSupported()) {
                Event::assertDispatchedTimes(BeforeTransactionEvent::class);
            }

            Event::assertDispatchedTimes(RegularEvent::class);
            Event::assertNotDispatched(AfterCommitEvent::class);

            Event::assertDispatched(TransactionBeginning::class);
            Event::assertDispatched(TransactionRolledBack::class);
            Event::assertNotDispatched(TransactionCommitting::class);
            Event::assertNotDispatched(TransactionCommitted::class);
        };

        $this->assertTransactionResult($callback, $assert);
    }

    private function assertTransactionCallbackResult(Closure $callback, Closure $assert, ?int $attempts = 1): void
    {
        $this->assertCallbackResultForConnection(
            DB::connection('sqlite'),
            $callback,
            $assert,
            $attempts,
        );

        $this->assertCallbackResultForConnection(
            DB::connection('mongodb'),
            $callback,
            $assert,
            $attempts,
        );
    }

    /**
     * Ensure equal transaction behavior between SQLite (handled by Laravel) and MongoDB
     */
    private function assertCallbackResultForConnection(Connection $connection, Closure $callback, Closure $assertions, int $attempts): void
    {
        $fake = Event::fake();
        $connection->setEventDispatcher($fake);

        if ($this->beforeStartingTransactionIsSupported()) {
            $connection->beforeStartingTransaction(function () {
                event(new BeforeTransactionEvent());
            });
        }

        try {
            $connection->transaction($callback, $attempts);
        } catch (Exception) {
        }

        $assertions();
    }

    private function assertTransactionResult(Closure $callback, Closure $assert): void
    {
        $this->assertManualResultForConnection(
            DB::connection('sqlite'),
            $callback,
            $assert,
        );

        $this->assertManualResultForConnection(
            DB::connection('mongodb'),
            $callback,
            $assert,
        );
    }

    /**
     * Ensure equal transaction behavior between SQLite (handled by Laravel) and MongoDB
     */
    private function assertManualResultForConnection(Connection $connection, Closure $callback, Closure $assert): void
    {
        $fake = Event::fake();
        $connection->setEventDispatcher($fake);

        if ($this->beforeStartingTransactionIsSupported()) {
            $connection->beforeStartingTransaction(function () {
                event(new BeforeTransactionEvent());
            });
        }

        $connection->beginTransaction();

        try {
            $callback();
            $connection->commit();
        } catch (Exception) {
            $connection->rollBack();
        }

        $assert();
    }

    private function beforeStartingTransactionIsSupported(): bool
    {
        return property_exists(ManagesTransactions::class, 'beforeStartingTransaction');
    }
}

class AfterCommitEvent implements ShouldDispatchAfterCommit
{
    use Dispatchable;
}

class BeforeTransactionEvent
{
    use Dispatchable;
}
class RegularEvent
{
    use Dispatchable;
}
class Fake extends RuntimeException
{
    public function __construct()
    {
        $this->errorLabels = ['TransientTransactionError'];
    }
}

if (interface_exists(ConcurrencyErrorDetector::class)) {
    class FakeConcurrencyErrorDetector implements ConcurrencyErrorDetector
    {
        public function causedByConcurrencyError(Throwable $e): bool
        {
            return true;
        }
    }
}
