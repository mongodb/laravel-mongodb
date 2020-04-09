<?php

declare(strict_types=1);

use Jenssegers\Mongodb\Schema\Blueprint;
class TransactionTest extends TestCase
{
    public $connection = 'mongodb_repl';

    public function setUp(): void
    {
        parent::setUp();

        Schema::create('users');
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
        parent::setUp();

        User::on($this->connection)->truncate();
        DB::collection('users')->truncate();
        Schema::drop('users');
    }

    public function testCommitTransaction(): void
    {
        /**
         * Insert Commit
         */
        try {
            DB::beginTransaction();

            User::on($this->connection)->insert([
                'name' => 'John Doe'
            ]);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            dd($e);

            $this->assertTrue(false);
        }

        $this->assertTrue(User::on($this->connection)->where('name', 'John Doe')->exists());

        /**
         * Update Commit
         */
        try {
            DB::beginTransaction();

            User::on($this->connection)->where('name', 'John Doe')->update([
                'name' => 'Jane Doe'
            ]);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            $this->assertTrue(false);
        }

        $this->assertTrue(User::on($this->connection)->where('name', 'Jane Doe')->exists());

        /**
         * Delete Commit
         */
        try {
            DB::beginTransaction();

            User::on($this->connection)->where('name', 'Jane Doe')->delete();

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            $this->assertTrue(false);
        }

        $this->assertFalse(User::on($this->connection)->where('name', 'Jane Doe')->exists());
    }

    public function testRollbackTransaction(): void
    {
        try {
            DB::beginTransaction();

            User::on($this->connection)->insert([
                'name' => 'John Doe'
            ]);

            DB::rollBack();
        } catch (Exception $e) {
            DB::rollBack();

            $this->assertTrue(false);
        }

        $this->assertFalse(User::on($this->connection)->where('name', 'John Doe')->exists());

        try {
            DB::beginTransaction();

            User::on($this->connection)->insert([
                'name' => 'John Doe'
            ]);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            $this->assertTrue(false);
        }

        $this->assertTrue(User::on($this->connection)->where('name', 'John Doe')->exists());

        try {
            DB::beginTransaction();

            User::on($this->connection)->where('name', 'John Doe')->update([
                'name' => 'Jane Doe'
            ]);

            DB::rollBack();
        } catch (Exception $e) {
            DB::rollBack();

            $this->assertTrue(false);
        }

        $this->assertTrue(User::on($this->connection)->where('name', 'John Doe')->exists());

        try {
            DB::beginTransaction();

            User::on($this->connection)->where('name', 'John Doe')->delete();

            DB::rollBack();
        } catch (Exception $e) {
            DB::rollBack();

            $this->assertTrue(false);
        }

        $this->assertTrue(User::on($this->connection)->where('name', 'John Doe')->exists());
    }
}
