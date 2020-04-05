<?php

declare(strict_types=1);

use Jenssegers\Mongodb\Schema\Blueprint;

class TransactionTest extends TestCase
{
    /**
     * Creating collection before data process is important in transaction of replica set.
     * Collection should have been created already or it will fail.
     * @see https://github.com/ilyasokay/docker-mongo-installation
     */

    public function tearDown(): void
    {
        Schema::drop('users');
    }

    public function testCreate(): void
    {
        Schema::create('users');
        $this->assertTrue(Schema::hasCollection('users'));
        $this->assertTrue(Schema::hasTable('users'));
    }

    public function testCreateWithCallback(): void
    {
        $instance = $this;

        Schema::create('users', function ($collection) use ($instance) {
            $instance->assertInstanceOf(Blueprint::class, $collection);
        });

        $this->assertTrue(Schema::hasCollection('users'));
    }

    public function testCommitWithTransaction(): void
    {
        Schema::drop('users');
        Schema::create('users');

        /**
         * Insert Commit
         */
        try {

            DB::beginTransaction();

            User::insert([
                'name' => 'John Doe'
            ]);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            $this->assertTrue(false);
        }

        $this->assertTrue(User::where('name', 'John Doe')->exists());

        /**
         * Update Commit
         */
        try {

            DB::beginTransaction();

            User::where('name', 'John Doe')->update([
                'name' => 'Jane Doe'
            ]);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            $this->assertTrue(false);
        }

        $this->assertTrue(User::where('name', 'Jane Doe')->exists());

        /**
         * Delete Commit
         */
        try {

            DB::beginTransaction();

            User::where('name', 'Jane Doe')->delete();

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            $this->assertTrue(false);
        }

        $this->assertFalse(User::where('name', 'Jane Doe')->exists());

    }

    public function testRollbackWithTransaction(): void
    {
        Schema::drop('users');
        Schema::create('users');

        try {

            DB::beginTransaction();

            User::insert([
                'name' => 'John Doe'
            ]);

            DB::rollBack();
        } catch (Exception $e) {
            DB::rollBack();

            $this->assertTrue(false);
        }

        $this->assertFalse(User::where('name', 'John Doe')->exists());

        try {

            DB::beginTransaction();

            User::insert([
                'name' => 'John Doe'
            ]);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            $this->assertTrue(false);
        }

        $this->assertTrue(User::where('name', 'John Doe')->exists());

        try {

            DB::beginTransaction();

            User::where('name', 'John Doe')->update([
                'name' => 'Jane Doe'
            ]);

            DB::rollBack();
        } catch (Exception $e) {
            DB::rollBack();

            $this->assertTrue(false);
        }

        $this->assertTrue(User::where('name', 'John Doe')->exists());

        try {

            DB::beginTransaction();

            User::where('name', 'John Doe')->delete();

            DB::rollBack();
        } catch (Exception $e) {
            DB::rollBack();

            $this->assertTrue(false);
        }

        $this->assertTrue(User::where('name', 'John Doe')->exists());
    }
}
