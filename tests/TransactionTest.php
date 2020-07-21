<?php
declare(strict_types=1);

use Illuminate\Support\Facades\DB;

class TransactionTest extends TestCase
{
    protected $insertData = ['name' => 'klinson', 'age' => 20, 'title' => 'admin'];
    protected $originData = ['name' => 'users', 'age' => 20, 'title' => 'user'];
    protected $connection = 'mongodb_repl';

    public function setUp(): void
    {
        parent::setUp();

        User::on($this->connection)->truncate();
        User::on($this->connection)->create($this->originData);
    }

    protected function getEnvironmentSetUp($app)
    {
        if (version_compare(env('MONGO_VERSION'), '4', '<')) {
            $this->markTestSkipped('MongoDB with version below 4 is not supported for transactions');
        }

        $config = require 'config/database.php';

        $app['config']->set('database.connections.'.$this->connection, $config['connections'][$this->connection]);
        $app['config']->set('database.default', $this->connection);
    }

    public function tearDown(): void
    {
        User::on($this->connection)->truncate();

        parent::tearDown();
    }

    public function testCreate()
    {
        /** rollback test */
        DB::beginTransaction();
        $user = User::on($this->connection)->create($this->insertData);
        DB::rollBack();
        $this->assertInstanceOf(\Jenssegers\Mongodb\Eloquent\Model::class, $user);
        $this->assertTrue($user->exists);
        $this->assertEquals($this->insertData['name'], $user->name);

        $check = User::on($this->connection)->find($user->_id);
        $this->assertNull($check);

        /** commit test */
        DB::beginTransaction();
        $user = User::on($this->connection)->create($this->insertData);
        DB::commit();
        $this->assertInstanceOf(\Jenssegers\Mongodb\Eloquent\Model::class, $user);
        $this->assertTrue($user->exists);
        $this->assertEquals($this->insertData['name'], $user->name);

        $check = User::on($this->connection)->find($user->_id);
        $this->assertNotNull($check);
        $this->assertEquals($user->name, $check->name);
    }

    public function testInsert()
    {
        /** rollback test */
        DB::beginTransaction();
        $user = User::on($this->connection)->getModel();
        $user->name = $this->insertData['name'];
        $user->title = $this->insertData['title'];
        $user->age = $this->insertData['age'];
        $user->save();
        DB::rollBack();

        $this->assertTrue($user->exists);
        $this->assertTrue(isset($user->_id));
        $this->assertIsString($user->_id);

        $check = User::on($this->connection)->find($user->_id);
        $this->assertNull($check);

        /** commit test */
        DB::beginTransaction();
        $user = User::on($this->connection)->getModel();
        $user->name = $this->insertData['name'];
        $user->title = $this->insertData['title'];
        $user->age = $this->insertData['age'];
        $user->save();
        DB::commit();

        $this->assertTrue($user->exists);
        $this->assertTrue(isset($user->_id));
        $this->assertIsString($user->_id);

        $check = User::on($this->connection)->find($user->_id);
        $this->assertNotNull($check);
        $this->assertEquals($check->name, $user->name);
        $this->assertEquals($check->age, $user->age);
        $this->assertEquals($check->title, $user->title);
    }

    public function testInsertWithId(): void
    {
        $id = 1;
        $check = User::on($this->connection)->find($id);
        $this->assertNull($check);

        /** rollback test */
        DB::beginTransaction();
        $user = User::on($this->connection)->getModel();
        $user->_id = $id;
        $user->name = $this->insertData['name'];
        $user->title = $this->insertData['title'];
        $user->age = $this->insertData['age'];
        $user->save();
        DB::rollBack();

        $this->assertTrue($user->exists);
        $this->assertEquals($id, $user->_id);
        $check = User::on($this->connection)->find($id);
        $this->assertNull($check);

        /** commit test */
        DB::beginTransaction();
        $user = User::on($this->connection)->getModel();
        $user->_id = $id;
        $user->name = $this->insertData['name'];
        $user->title = $this->insertData['title'];
        $user->age = $this->insertData['age'];
        $user->save();
        DB::commit();

        $this->assertTrue($user->exists);
        $this->assertEquals($id, $user->_id);
        $check = User::on($this->connection)->find($id);
        $this->assertNotNull($check);
        $this->assertEquals($check->name, $user->name);
        $this->assertEquals($check->age, $user->age);
        $this->assertEquals($check->title, $user->title);
    }

    public function testUpdate()
    {
        /** rollback test */
        $new_age = $this->insertData['age'] + 1;
        $user1 = User::on($this->connection)->create($this->insertData);
        $user2 = User::on($this->connection)->create($this->insertData);
        DB::beginTransaction();
        $user1->age = $new_age;
        $user1->save();
        $user2->update(['age' => $new_age]);
        DB::rollBack();
        $this->assertEquals($new_age, $user1->age);
        $this->assertEquals($new_age, $user2->age);

        $check1 = User::on($this->connection)->find($user1->_id);
        $check2 = User::on($this->connection)->find($user2->_id);
        $this->assertEquals($this->insertData['age'], $check1->age);
        $this->assertEquals($this->insertData['age'], $check2->age);

        /** commit test */
        User::on($this->connection)->truncate();
        $user1 = User::on($this->connection)->create($this->insertData);
        $user2 = User::on($this->connection)->create($this->insertData);
        DB::beginTransaction();
        $user1->age = $new_age;
        $user1->save();
        $user2->update(['age' => $new_age]);
        DB::commit();
        $this->assertEquals($new_age, $user1->age);
        $this->assertEquals($new_age, $user2->age);

        $check1 = User::on($this->connection)->find($user1->_id);
        $check2 = User::on($this->connection)->find($user2->_id);
        $this->assertEquals($new_age, $check1->age);
        $this->assertEquals($new_age, $check2->age);
    }

    public function testDelete()
    {
        /** rollback test */
        $user1 = User::on($this->connection)->create($this->insertData);
        DB::beginTransaction();
        $user1->delete();
        DB::rollBack();
        $check1 = User::on($this->connection)->find($user1->_id);
        $this->assertNotNull($check1);

        /** commit test */
        User::on($this->connection)->truncate();
        $user1 = User::on($this->connection)->create($this->insertData);
        DB::beginTransaction();
        $user1->delete();
        DB::commit();
        $check1 = User::on($this->connection)->find($user1->_id);
        $this->assertNull($check1);
    }

    public function testTransaction()
    {
        $count = User::on($this->connection)->count();
        $this->assertEquals(1, $count);
        $new_age = $this->originData['age'] + 1;
        DB::transaction(function () use ($new_age) {
            User::on($this->connection)->create($this->insertData);
            User::on($this->connection)->where($this->originData['name'])->update(['age' => $new_age]);
        });
        $count = User::on($this->connection)->count();
        $this->assertEquals(2, $count);

        $checkInsert = User::on($this->connection)->where($this->insertData['name'])->first();
        $this->assertNotNull($checkInsert);

        $checkUpdate = User::on($this->connection)->where($this->originData['name'])->first();
        $this->assertEquals($new_age, $checkUpdate->age);
    }
}
