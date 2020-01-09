<?php
declare(strict_types=1);

use Illuminate\Support\Facades\DB;

class TransactionBuilderTest extends TestCase
{
    protected $insertData = ['name' => 'klinson', 'age' => 20, 'title' => 'admin'];
    protected $originData = ['name' => 'users', 'age' => 20, 'title' => 'user'];
    protected $connection = 'mongodb_replset';
    protected $originConnection = 'mongodb';

    public function setUp(): void
    {
        parent::setUp();

        /** change connection to seplset? because the transaction needs  */
        $this->originConnection = DB::getDefaultConnection();
        DB::setDefaultConnection($this->connection);

        DB::collection('users')->truncate();
        DB::collection('users')->insert($this->originData);
    }

    public function tearDown(): void
    {
        DB::collection('users')->truncate();
        DB::setDefaultConnection($this->originConnection);
        parent::tearDown();
    }

    public function testInsert()
    {
        /** rollback test */
        DB::beginTransaction();
        DB::collection('users')->insert($this->insertData);
        DB::rollBack();
        $users = DB::collection('users')->where('name', $this->insertData['name'])->get();
        $this->assertCount(0, $users);

        /** commit test */
        DB::beginTransaction();
        DB::collection('users')->insert($this->insertData);
        DB::commit();
        $users = DB::collection('users')->where('name', $this->insertData['name'])->get();
        $this->assertCount(1, $users);
    }

    public function testInsertGetId()
    {
        /** rollback test */
        DB::beginTransaction();
        $user_id = DB::collection('users')->insertGetId($this->insertData);
        DB::rollBack();
        $user_id = (string) $user_id;
        $user = DB::collection('users')->find($user_id);
        $this->assertNull($user);

        /** commit test */
        DB::beginTransaction();
        $user_id = DB::collection('users')->insertGetId($this->insertData);
        DB::commit();
        $user_id = (string) $user_id;
        $user = DB::collection('users')->find($user_id);
        $this->assertEquals($this->insertData['name'], $user['name']);
    }

    public function testUpdate()
    {
        /** rollback test */
        $new_age = $this->originData['age'] + 1;
        DB::beginTransaction();
        DB::collection('users')->where('name', $this->originData['name'])->update(['age' => $new_age]);
        DB::rollBack();
        $users = DB::collection('users')->where('name', $this->originData['name'])->where('age', $new_age)->get();
        $this->assertCount(0, $users);

        /** commit test */
        DB::beginTransaction();
        DB::collection('users')->where('name', $this->originData['name'])->update(['age' => $new_age]);
        DB::commit();
        $users = DB::collection('users')->where('name', $this->originData['name'])->where('age', $new_age)->get();
        $this->assertCount(1, $users);
    }

    public function testDelete()
    {
        /** rollback test */
        DB::beginTransaction();
        DB::collection('users')->where('name', $this->originData['name'])->delete();
        DB::rollBack();
        $users = DB::collection('users')->where('name', $this->originData['name'])->get();
        $this->assertCount(1, $users);

        /** commit test */
        DB::beginTransaction();
        DB::collection('users')->where('name', $this->originData['name'])->delete();
        DB::commit();
        $users = DB::collection('users')->where('name', $this->originData['name'])->get();
        $this->assertCount(0, $users);
    }

    public function testIncrement()
    {
        /** rollback test */
        DB::beginTransaction();
        DB::collection('users')->where('name', $this->originData['name'])->increment('age');
        DB::rollBack();
        $user = DB::collection('users')->where('name', $this->originData['name'])->first();
        $this->assertEquals($this->originData['age'], $user['age']);

        /** commit test */
        DB::beginTransaction();
        DB::collection('users')->where('name', $this->originData['name'])->increment('age');
        DB::commit();
        $user = DB::collection('users')->where('name', $this->originData['name'])->first();
        $this->assertEquals($this->originData['age'] + 1, $user['age']);
    }

    public function testDecrement()
    {
        /** rollback test */
        DB::beginTransaction();
        DB::collection('users')->where('name', $this->originData['name'])->decrement('age');
        DB::rollBack();
        $user = DB::collection('users')->where('name', $this->originData['name'])->first();
        $this->assertEquals($this->originData['age'], $user['age']);

        /** commit test */
        DB::beginTransaction();
        DB::collection('users')->where('name', $this->originData['name'])->decrement('age');
        DB::commit();
        $user = DB::collection('users')->where('name', $this->originData['name'])->first();
        $this->assertEquals($this->originData['age'] - 1, $user['age']);
    }

    public function testQuery()
    {
        /** rollback test */
        DB::beginTransaction();
        $count = DB::collection('users')->count();
        $this->assertEquals(1, $count);
        DB::collection('users')->insert($this->insertData);
        $count = DB::collection('users')->count();
        $this->assertEquals(2, $count);
        DB::rollBack();
        $count = DB::collection('users')->count();
        $this->assertEquals(1, $count);

        /** commit test */
        DB::beginTransaction();
        $count = DB::collection('users')->count();
        $this->assertEquals(1, $count);
        DB::collection('users')->insert($this->insertData);
        $count = DB::collection('users')->count();
        $this->assertEquals(2, $count);
        DB::commit();
        $count = DB::collection('users')->count();
        $this->assertEquals(2, $count);
    }

    public function testTransaction()
    {
        $count = DB::collection('users')->count();
        $this->assertEquals(1, $count);

        $new_age = $this->originData['age'] + 1;
        DB::transaction(function () use ($new_age) {
            DB::collection('users')->insert($this->insertData);
            DB::collection('users')->where('name', $this->originData['name'])->update(['age' => $new_age]);
        });
        $count = DB::collection('users')->count();
        $this->assertEquals(2, $count);

        $checkInsert = DB::collection('users')->where('name', $this->insertData['name'])->first();
        $this->assertNotNull($checkInsert);
        $this->assertEquals($this->insertData['age'], $checkInsert['age']);

        $checkUpdate = DB::collection('users')->where('name', $this->originData['name'])->first();
        $this->assertEquals($new_age, $checkUpdate['age']);
    }

    /**
     * Supports infinite-level nested transactions, but outside transaction rollbacks do not affect the commit of inside transactions
     */
    public function TestNestingTransaction()
    {
        DB::collection('users')->insert($this->insertData);
        $new_age1 = $this->originData['age'] + 1;
        $new_age2 = $this->insertData['age'] + 1;
        /** outside transaction */
        DB::beginTransaction();

        DB::collection('users')->where('name', $this->originData['name'])->update(['age' => $new_age1]);

        /** inside transaction */
        DB::transaction(function () use ($new_age2) {
            DB::collection('users')->where('name', $this->insertData['name'])->update(['age' => $new_age2]);
        });

        DB::rollBack();

        $check1 = DB::collection('users')->where('name', $this->originData['name'])->first();
        $check2 = DB::collection('users')->where('name', $this->insertData['name'])->first();
        $this->assertNotEquals($new_age1, $check1['age']);
        $this->assertEquals($new_age2, $check2['age']);
    }
}
