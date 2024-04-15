<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Support\Facades\DB;
use MongoDB\Laravel\Tests\TestCase;

class TransactionsTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testTransactionCallback(): void
    {
        require_once __DIR__ . '/Account.php';

        Account::truncate();

        Account::insert([
            [
                'number' => 223344,
                'balance' => 5000,
            ],
            [
                'number' => 776655,
                'balance' => 100,
            ],
        ]);

        // begin transaction callback
        DB::transaction(function () {
            $transferAmount = 200;

            $sender = Account::where('number', 223344)->first();
            $sender->balance -= $transferAmount;
            $sender->save();

            $receiver = Account::where('number', 776655)->first();
            $receiver->balance += $transferAmount;
            $receiver->save();
        });
        // end transaction callback

        $sender = Account::where('number', 223344)->first();
        $receiver = Account::where('number', 776655)->first();

        $this->assertEquals(4800, $sender->balance);
        $this->assertEquals(300, $receiver->balance);
    }

    public function testTransactionCommit(): void
    {
        require_once __DIR__ . '/Account.php';

        Account::truncate();

        Account::insert([
            [
                'number' => 223344,
                'balance' => 5000,
            ],
            [
                'number' => 776655,
                'balance' => 100,
            ],
        ]);

        // begin commit transaction
        DB::beginTransaction();
        $oldAccount = Account::where('number', 223344)->first();

        $newAccount = Account::where('number', 776655)->first();
        $newAccount->balance += $oldAccount->balance;
        $newAccount->save();

        $oldAccount->delete();
        DB::commit();
        // end commit transaction

        $acct1 = Account::where('number', 223344)->first();
        $acct2 = Account::where('number', 776655)->first();

        $this->assertNull($acct1);
        $this->assertEquals(5100, $acct2->balance);
    }

    public function testTransactionRollback(): void
    {
        require_once __DIR__ . '/Account.php';

        Account::truncate();
        Account::insert([
            [
                'number' => 223344,
                'balance' => 200,
            ],
            [
                'number' => 776655,
                'balance' => 0,
            ],
            [
                'number' => 990011,
                'balance' => 0,
            ],
        ]);

        // begin rollback transaction
        DB::beginTransaction();

        $sender = Account::where('number', 223344)->first();
        $receiverA = Account::where('number', 776655)->first();
        $receiverB = Account::where('number', 990011)->first();

        $amountA = 100;
        $amountB = 200;

        $sender->balance -= $amountA;
        $receiverA->balance += $amountA;

        $sender->balance -= $amountB;
        $receiverB->balance += $amountB;

        if ($sender->balance < 0) {
            // insufficient balance, roll back the transaction
            DB::rollback();
        } else {
            DB::commit();
        }

        // end rollback transaction

        $sender = Account::where('number', 223344)->first();
        $receiverA = Account::where('number', 776655)->first();
        $receiverB = Account::where('number', 990011)->first();

        $this->assertEquals(200, $sender->balance);
        $this->assertEquals(0, $receiverA->balance);
        $this->assertEquals(0, $receiverB->balance);
    }
}
