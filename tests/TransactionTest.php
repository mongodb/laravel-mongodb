<?php

use Illuminate\Support\Facades\DB;
use Jenssegers\Mongodb\Connection;
use Jenssegers\Mongodb\Eloquent\Model;
use MongoDB\BSON\ObjectId;
use MongoDB\Driver\Exception\BulkWriteException;
use MongoDB\Driver\Server;

class TransactionTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        if ($this->getPrimaryServerType() === Server::TYPE_STANDALONE) {
            $this->markTestSkipped('Transactions are not supported on standalone servers');
        }

        User::truncate();
    }

    public function tearDown(): void
    {
        User::truncate();

        parent::tearDown();
    }

    public function testCreateWithCommit(): void
    {
        DB::beginTransaction();
        /** @var User $klinson */
        $klinson = User::create(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);
        DB::commit();

        $this->assertInstanceOf(Model::class, $klinson);
        $this->assertTrue($klinson->exists);
        $this->assertEquals('klinson', $klinson->name);

        $check = User::find($klinson->_id);
        $this->assertInstanceOf(User::class, $check);
        $this->assertEquals($klinson->name, $check->name);
    }

    public function testCreateRollBack(): void
    {
        DB::beginTransaction();
        /** @var User $klinson */
        $klinson = User::create(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);
        DB::rollBack();

        $this->assertInstanceOf(Model::class, $klinson);
        $this->assertTrue($klinson->exists);
        $this->assertEquals('klinson', $klinson->name);

        $this->assertFalse(User::where('_id', $klinson->_id)->exists());
    }

    public function testInsertWithCommit(): void
    {
        DB::beginTransaction();
        DB::collection('users')->insert(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);
        DB::commit();

        $this->assertTrue(DB::collection('users')->where('name', 'klinson')->exists());
    }

    public function testInsertWithRollBack(): void
    {
        DB::beginTransaction();
        DB::collection('users')->insert(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);
        DB::rollBack();

        $this->assertFalse(DB::collection('users')->where('name', 'klinson')->exists());
    }

    public function testEloquentCreateWithCommit(): void
    {
        DB::beginTransaction();
        /** @var User $klinson */
        $klinson = User::getModel();
        $klinson->name = 'klinson';
        $klinson->save();
        DB::commit();

        $this->assertTrue($klinson->exists);
        $this->assertNotNull($klinson->getIdAttribute());

        $check = User::find($klinson->_id);
        $this->assertInstanceOf(User::class, $check);
        $this->assertEquals($check->name, $klinson->name);
    }

    public function testEloquentCreateWithRollBack(): void
    {
        DB::beginTransaction();
        /** @var User $klinson */
        $klinson = User::getModel();
        $klinson->name = 'klinson';
        $klinson->save();
        DB::rollBack();

        $this->assertTrue($klinson->exists);
        $this->assertNotNull($klinson->getIdAttribute());

        $this->assertFalse(User::where('_id', $klinson->_id)->exists());
    }

    public function testInsertGetIdWithCommit(): void
    {
        DB::beginTransaction();
        $userId = DB::collection('users')->insertGetId(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);
        DB::commit();

        $this->assertInstanceOf(ObjectId::class, $userId);

        $user = DB::collection('users')->find((string) $userId);
        $this->assertEquals('klinson', $user['name']);
    }

    public function testInsertGetIdWithRollBack(): void
    {
        DB::beginTransaction();
        $userId = DB::collection('users')->insertGetId(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);
        DB::rollBack();

        $this->assertInstanceOf(ObjectId::class, $userId);
        $this->assertFalse(DB::collection('users')->where('_id', (string) $userId)->exists());
    }

    public function testUpdateWithCommit(): void
    {
        User::create(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);

        DB::beginTransaction();
        $updated = DB::collection('users')->where('name', 'klinson')->update(['age' => 21]);
        DB::commit();

        $this->assertEquals(1, $updated);
        $this->assertTrue(DB::collection('users')->where('name', 'klinson')->where('age', 21)->exists());
    }

    public function testUpdateWithRollback(): void
    {
        User::create(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);

        DB::beginTransaction();
        $updated = DB::collection('users')->where('name', 'klinson')->update(['age' => 21]);
        DB::rollBack();

        $this->assertEquals(1, $updated);
        $this->assertFalse(DB::collection('users')->where('name', 'klinson')->where('age', 21)->exists());
    }

    public function testEloquentUpdateWithCommit(): void
    {
        /** @var User $klinson */
        $klinson = User::create(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);
        /** @var User $alcaeus */
        $alcaeus = User::create(['name' => 'alcaeus', 'age' => 38, 'title' => 'admin']);

        DB::beginTransaction();
        $klinson->age = 21;
        $klinson->update();

        $alcaeus->update(['age' => 39]);
        DB::commit();

        $this->assertEquals(21, $klinson->age);
        $this->assertEquals(39, $alcaeus->age);

        $this->assertTrue(User::where('_id', $klinson->_id)->where('age', 21)->exists());
        $this->assertTrue(User::where('_id', $alcaeus->_id)->where('age', 39)->exists());
    }

    public function testEloquentUpdateWithRollBack(): void
    {
        /** @var User $klinson */
        $klinson = User::create(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);
        /** @var User $alcaeus */
        $alcaeus = User::create(['name' => 'klinson', 'age' => 38, 'title' => 'admin']);

        DB::beginTransaction();
        $klinson->age = 21;
        $klinson->update();

        $alcaeus->update(['age' => 39]);
        DB::rollBack();

        $this->assertEquals(21, $klinson->age);
        $this->assertEquals(39, $alcaeus->age);

        $this->assertFalse(User::where('_id', $klinson->_id)->where('age', 21)->exists());
        $this->assertFalse(User::where('_id', $alcaeus->_id)->where('age', 39)->exists());
    }

    public function testDeleteWithCommit(): void
    {
        User::create(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);

        DB::beginTransaction();
        $deleted = User::where(['name' => 'klinson'])->delete();
        DB::commit();

        $this->assertEquals(1, $deleted);
        $this->assertFalse(User::where(['name' => 'klinson'])->exists());
    }

    public function testDeleteWithRollBack(): void
    {
        User::create(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);

        DB::beginTransaction();
        $deleted = User::where(['name' => 'klinson'])->delete();
        DB::rollBack();

        $this->assertEquals(1, $deleted);
        $this->assertTrue(User::where(['name' => 'klinson'])->exists());
    }

    public function testEloquentDeleteWithCommit(): void
    {
        /** @var User $klinson */
        $klinson = User::create(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);

        DB::beginTransaction();
        $klinson->delete();
        DB::commit();

        $this->assertFalse(User::where('_id', $klinson->_id)->exists());
    }

    public function testEloquentDeleteWithRollBack(): void
    {
        /** @var User $klinson */
        $klinson = User::create(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);

        DB::beginTransaction();
        $klinson->delete();
        DB::rollBack();

        $this->assertTrue(User::where('_id', $klinson->_id)->exists());
    }

    public function testIncrementWithCommit(): void
    {
        User::create(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);

        DB::beginTransaction();
        DB::collection('users')->where('name', 'klinson')->increment('age');
        DB::commit();

        $this->assertTrue(DB::collection('users')->where('name', 'klinson')->where('age', 21)->exists());
    }

    public function testIncrementWithRollBack(): void
    {
        User::create(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);

        DB::beginTransaction();
        DB::collection('users')->where('name', 'klinson')->increment('age');
        DB::rollBack();

        $this->assertTrue(DB::collection('users')->where('name', 'klinson')->where('age', 20)->exists());
    }

    public function testDecrementWithCommit(): void
    {
        User::create(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);

        DB::beginTransaction();
        DB::collection('users')->where('name', 'klinson')->decrement('age');
        DB::commit();

        $this->assertTrue(DB::collection('users')->where('name', 'klinson')->where('age', 19)->exists());
    }

    public function testDecrementWithRollBack(): void
    {
        User::create(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);

        DB::beginTransaction();
        DB::collection('users')->where('name', 'klinson')->decrement('age');
        DB::rollBack();

        $this->assertTrue(DB::collection('users')->where('name', 'klinson')->where('age', 20)->exists());
    }

    public function testQuery()
    {
        /** rollback test */
        DB::beginTransaction();
        $count = DB::collection('users')->count();
        $this->assertEquals(0, $count);
        DB::collection('users')->insert(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);
        $count = DB::collection('users')->count();
        $this->assertEquals(1, $count);
        DB::rollBack();

        $count = DB::collection('users')->count();
        $this->assertEquals(0, $count);

        /** commit test */
        DB::beginTransaction();
        $count = DB::collection('users')->count();
        $this->assertEquals(0, $count);
        DB::collection('users')->insert(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);
        $count = DB::collection('users')->count();
        $this->assertEquals(1, $count);
        DB::commit();

        $count = DB::collection('users')->count();
        $this->assertEquals(1, $count);
    }

    public function testTransaction(): void
    {
        User::create(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);

        // The $connection parameter may be unused, but is implicitly used to
        // test that the closure is executed with the connection as an argument.
        DB::transaction(function (Connection $connection): void {
            User::create(['name' => 'alcaeus', 'age' => 38, 'title' => 'admin']);
            User::where(['name' => 'klinson'])->update(['age' => 21]);
        });

        $count = User::count();
        $this->assertEquals(2, $count);

        $this->assertTrue(User::where('alcaeus')->exists());
        $this->assertTrue(User::where(['name' => 'klinson'])->where('age', 21)->exists());
    }

    public function testTransactionRepeatsOnTransientFailure(): void
    {
        User::create(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);

        $timesRun = 0;

        DB::transaction(function () use (&$timesRun): void {
            $timesRun++;

            // Run a query to start the transaction on the server
            User::create(['name' => 'alcaeus', 'age' => 38, 'title' => 'admin']);

            // Update user outside of the session
            if ($timesRun == 1) {
                DB::getCollection('users')->updateOne(['name' => 'klinson'], ['$set' => ['age' => 22]]);
            }

            // This update will create a write conflict, aborting the transaction
            User::where(['name' => 'klinson'])->update(['age' => 21]);
        }, 2);

        $this->assertSame(2, $timesRun);
        $this->assertTrue(User::where(['name' => 'klinson'])->where('age', 21)->exists());
    }

    public function testTransactionRespectsRepetitionLimit(): void
    {
        $klinson = User::create(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);

        $timesRun = 0;

        try {
            DB::transaction(function () use (&$timesRun): void {
                $timesRun++;

                // Run a query to start the transaction on the server
                User::create(['name' => 'alcaeus', 'age' => 38, 'title' => 'admin']);

                // Update user outside of the session
                DB::getCollection('users')->updateOne(['name' => 'klinson'], ['$inc' => ['age' => 2]]);

                // This update will create a write conflict, aborting the transaction
                User::where(['name' => 'klinson'])->update(['age' => 21]);
            }, 2);

            $this->fail('Expected exception during transaction');
        } catch (BulkWriteException $e) {
            $this->assertInstanceOf(BulkWriteException::class, $e);
            $this->assertStringContainsString('WriteConflict', $e->getMessage());
        }

        $this->assertSame(2, $timesRun);

        $check = User::find($klinson->_id);
        $this->assertInstanceOf(User::class, $check);

        // Age is expected to be 24: the callback is executed twice, incrementing age by 2 every time
        $this->assertSame(24, $check->age);
    }

    public function testTransactionReturnsCallbackResult(): void
    {
        $result = DB::transaction(function (): User {
            return User::create(['name' => 'klinson', 'age' => 20, 'title' => 'admin']);
        });

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($result->title, 'admin');
        $this->assertSame(1, User::count());
    }

    public function testNestedTransactionsCauseException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Transaction already in progress');

        DB::beginTransaction();
        DB::beginTransaction();
        DB::commit();
        DB::rollBack();
    }

    public function testNestingTransactionInManualTransaction()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Transaction already in progress');

        DB::beginTransaction();
        DB::transaction(function (): void {
        });
        DB::rollBack();
    }

    public function testCommitWithoutSession(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('There is no active session.');

        DB::commit();
    }

    public function testRollBackWithoutSession(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('There is no active session.');

        DB::rollback();
    }

    private function getPrimaryServerType(): int
    {
        return DB::getMongoClient()->getManager()->selectServer()->getType();
    }
}
