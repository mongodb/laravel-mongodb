<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class DatabaseEloquentTimestampsTest extends TestCase
{
    /**
     * Tear down the database schema.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        User::truncate();
    }

    /**
     * Tests...
     */
    public function testUserWithCreatedAtAndUpdatedAt()
    {
        $now = Carbon::now();
        $user = UserWithCreatedAndUpdated::create([
            'email' => 'test@test.com',
        ]);

        $this->assertEquals($now->toDateTimeString(), $user->created_at->toDateTimeString());
        $this->assertEquals($now->toDateTimeString(), $user->updated_at->toDateTimeString());
    }

    public function testUserWithCreatedAt()
    {
        $now = Carbon::now();
        $user = UserWithCreated::create([
            'email' => 'test@test.com',
        ]);

        $this->assertEquals($now->toDateTimeString(), $user->created_at->toDateTimeString());
    }

    public function testUserWithUpdatedAt()
    {
        $now = Carbon::now();
        $user = UserWithUpdated::create([
            'email' => 'test@test.com',
        ]);

        $this->assertEquals($now->toDateTimeString(), $user->updated_at->toDateTimeString());
    }
}

/**
 * Eloquent Models...
 */
class UserWithCreatedAndUpdated extends Eloquent
{
    protected $collection = 'users';

    protected $guarded = [];
}

class UserWithCreated extends Eloquent
{
    public const UPDATED_AT = null;

    protected $collection = 'users_created_at';

    protected $guarded = [];

    protected $dateFormat = 'U';
}

class UserWithUpdated extends Eloquent
{
    public const CREATED_AT = null;

    protected $collection = 'users_updated_at';

    protected $guarded = [];

    protected $dateFormat = 'U';
}
