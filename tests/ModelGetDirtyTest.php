<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests;

use Carbon\Carbon;
use DateTime;
use MongoDB\Laravel\Tests\Models\Casting;
use MongoDB\Laravel\Tests\Models\MemberStatus;
use MongoDB\Laravel\Tests\Models\Options;
use MongoDB\Laravel\Tests\Models\User;

class ModelGetDirtyTest extends TestCase
{
    protected function tearDown(): void
    {
        User::truncate();
        Casting::truncate();

        parent::tearDown();
    }

    public function testGetDirtyDates(): void
    {
        $user = new User();
        $user->name = 'John Doe';
        $user->birthday = new DateTime('19 august 1989');
        $user->syncOriginal();

        // Same date: not dirty
        $this->assertEmpty($user->getDirty());

        $user->birthday = new DateTime('19 august 1989');
        // Same date set again: not dirty
        $this->assertEmpty($user->getDirty());
    }

    public function testGetDirtyObjects(): void
    {
        $user = new User();
        $user->options = new Options();
        // New unsaved model: dirty
        $this->assertNotEmpty($user->getDirty());

        $user->save();
        // After save: not dirty
        $this->assertEmpty($user->getDirty());

        // Different object value: dirty
        $user->options = (new Options())->setOption1('Value1');
        $this->assertNotEmpty($user->getDirty());

        $user->save();
        // After save: not dirty
        $this->assertEmpty($user->getDirty());
    }

    public function testGetDirtyScalarTypeChange(): void
    {
        // Changing a scalar value from one type to another must be considered dirty
        // because MongoDB stores types as-is (int 1 and string '1' are different).
        $user = new User();
        $user->name = 'John Doe';
        $user->age = 25;
        $user->syncOriginal();

        $this->assertEmpty($user->getDirty());

        // Same value, same type: not dirty
        $user->age = 25;
        $this->assertEmpty($user->getDirty());

        // Same numeric value, different type: dirty
        $user->age = '25';
        $this->assertTrue($user->isDirty('age'));
    }

    public function testGetDirtyEmbeddedDocument(): void
    {
        $user = User::create(['name' => 'John Doe', 'address' => ['city' => 'Paris', 'country' => 'France']]);

        $user = User::find($user->id);
        $this->assertFalse($user->isDirty());

        // Setting the same array value: not dirty
        $user->address = ['city' => 'Paris', 'country' => 'France'];
        $this->assertFalse($user->isDirty());

        // Changing a nested value: dirty
        $user->address = ['city' => 'Lyon', 'country' => 'France'];
        $this->assertTrue($user->isDirty('address'));
    }

    public function testGetDirtyDatetimeCast(): void
    {
        $user = User::create(['name' => 'John Doe', 'birthday' => new DateTime('1989-08-19 12:00:00')]);
        $user = User::find($user->id);
        $this->assertFalse($user->isDirty());

        // Same date via Carbon: not dirty
        $user->birthday = Carbon::parse('1989-08-19 12:00:00');
        $this->assertFalse($user->isDirty('birthday'));

        // Same date via DateTime: not dirty
        $user->birthday = new DateTime('1989-08-19 12:00:00');
        $this->assertFalse($user->isDirty('birthday'));

        // Different date: dirty
        $user->birthday = new DateTime('1990-01-01 00:00:00');
        $this->assertTrue($user->isDirty('birthday'));

        // Null vs date: dirty
        $user->save();
        $user->birthday = null;
        $this->assertTrue($user->isDirty('birthday'));
    }

    public function testGetDirtyEnumCast(): void
    {
        $user = User::create(['name' => 'John Doe', 'member_status' => MemberStatus::Member]);
        $user = User::find($user->id);
        $this->assertFalse($user->isDirty());

        // Same enum value: not dirty
        $user->member_status = MemberStatus::Member;
        $this->assertFalse($user->isDirty('member_status'));

        // Setting null: dirty
        $user->member_status = null;
        $this->assertTrue($user->isDirty('member_status'));
    }

    public function testGetDirtyWithPrimitiveCast(): void
    {
        $casting = Casting::create(['intNumber' => 1, 'floatNumber' => 1.5, 'stringContent' => 'hello', 'booleanValue' => true]);
        $casting = Casting::find($casting->id);
        $this->assertFalse($casting->isDirty());

        // Same value, different PHP type: cast normalizes to the same value, so not dirty
        $casting->intNumber = '1';
        $this->assertFalse($casting->isDirty('intNumber'));

        $casting->booleanValue = 1;
        $this->assertFalse($casting->isDirty('booleanValue'));

        // Different effective value: dirty
        $casting->intNumber = 2;
        $this->assertTrue($casting->isDirty('intNumber'));

        $casting->floatNumber = 1.6;
        $this->assertTrue($casting->isDirty('floatNumber'));
    }

    public function testGetDirtyWithObjectAndArrayCast(): void
    {
        $casting = Casting::create(['objectValue' => (object) ['x' => 1], 'arrayValue' => [1, 2, 3]]);
        $casting = Casting::find($casting->id);
        $this->assertFalse($casting->isDirty());

        // Same content via different PHP type (array vs stdClass): BSON encoding makes them equivalent
        $casting->objectValue = (object) ['x' => 1];
        $this->assertFalse($casting->isDirty('objectValue'));

        $casting->arrayValue = [1, 2, 3];
        $this->assertFalse($casting->isDirty('arrayValue'));

        // Different content: dirty
        $casting->objectValue = (object) ['x' => 2];
        $this->assertTrue($casting->isDirty('objectValue'));

        $casting->arrayValue = [1, 2, 4];
        $this->assertTrue($casting->isDirty('arrayValue'));
    }

    public function testGetDirtyDateWithoutCast(): void
    {
        // A date field stored as UTCDateTime in MongoDB without an explicit cast.
        // When reloaded, $original contains a UTCDateTime. Assigning a Carbon/DateTime
        // triggers the DateTimeInterface -> UTCDateTime conversion before Document::fromPHP ==.
        $user = User::create(['name' => 'John Doe', 'registered_at' => new DateTime('2024-01-15 12:00:00')]);
        $user = User::find($user->id);
        $this->assertFalse($user->isDirty());

        // Same date via Carbon: not dirty
        $user->registered_at = Carbon::parse('2024-01-15 12:00:00');
        $this->assertFalse($user->isDirty('registered_at'));

        // Same date via DateTime: not dirty
        $user->registered_at = new DateTime('2024-01-15 12:00:00');
        $this->assertFalse($user->isDirty('registered_at'));

        // Different date: dirty
        $user->registered_at = new DateTime('2024-01-16 00:00:00');
        $this->assertTrue($user->isDirty('registered_at'));
    }
}
