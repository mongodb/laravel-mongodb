<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Ticket;

use Illuminate\Support\Facades\DB;
use MongoDB\Driver\WriteConcern;
use MongoDB\Laravel\Tests\TestCase;

/**
 * insert(), insertGetId() and delete() accept driver $options like update() does.
 *
 * @see https://github.com/mongodb/laravel-mongodb/issues/2030
 */
class GH2030Test extends TestCase
{
    public function tearDown(): void
    {
        DB::table('gh2030_characters')->truncate();

        parent::tearDown();
    }

    public function testInsertAcceptsWriteConcernOption(): void
    {
        $acknowledged = DB::table('gh2030_characters')->insert(
            ['_id' => 'unique21', 'name' => 'async'],
            ['writeConcern' => new WriteConcern(0)],
        );

        // Unacknowledged write: driver does not wait for server ack
        $this->assertFalse($acknowledged);

        // Document is still written (fire-and-forget)
        $this->assertSame(1, DB::table('gh2030_characters')->where('_id', 'unique21')->count());
    }

    public function testInsertWithoutOptionsRemainsAcknowledged(): void
    {
        $this->assertTrue(DB::table('gh2030_characters')->insert(['name' => 'sync']));
        $this->assertSame(1, DB::table('gh2030_characters')->count());
    }

    public function testInsertGetIdAcceptsWriteConcernOption(): void
    {
        $id = DB::table('gh2030_characters')->insertGetId(
            ['name' => 'async-one'],
            null,
            ['writeConcern' => new WriteConcern(0)],
        );

        $this->assertNull($id);
        $this->assertSame(1, DB::table('gh2030_characters')->where('name', 'async-one')->count());
    }

    public function testDeleteAcceptsWriteConcernOption(): void
    {
        DB::table('gh2030_characters')->insert(['_id' => 'to-delete', 'name' => 'x']);

        $deleted = DB::table('gh2030_characters')->delete(
            'to-delete',
            ['writeConcern' => new WriteConcern(0)],
        );

        $this->assertSame(0, $deleted);
        $this->assertSame(0, DB::table('gh2030_characters')->where('_id', 'to-delete')->count());
    }

    public function testDeleteManyAcceptsWriteConcernOption(): void
    {
        DB::table('gh2030_characters')->insert([
            ['name' => 'a', 'team' => 'red'],
            ['name' => 'b', 'team' => 'red'],
        ]);

        $deleted = DB::table('gh2030_characters')
            ->where('team', 'red')
            ->delete(null, ['writeConcern' => new WriteConcern(0)]);

        $this->assertSame(0, $deleted);
        $this->assertSame(0, DB::table('gh2030_characters')->where('team', 'red')->count());
    }

    public function testOptionsMethodIsMergedIntoInsertAndDelete(): void
    {
        $acknowledged = DB::table('gh2030_characters')
            ->options(['writeConcern' => new WriteConcern(0)])
            ->insert(['_id' => 'via-options', 'name' => 'chained']);

        $this->assertFalse($acknowledged);
        $this->assertSame(1, DB::table('gh2030_characters')->where('_id', 'via-options')->count());

        $deleted = DB::table('gh2030_characters')
            ->options(['writeConcern' => new WriteConcern(0)])
            ->where('_id', 'via-options')
            ->delete();

        $this->assertSame(0, $deleted);
        $this->assertSame(0, DB::table('gh2030_characters')->where('_id', 'via-options')->count());
    }

    public function testUpdateAlreadyAcceptsOptions(): void
    {
        DB::table('gh2030_characters')->insert(['_id' => 'u1', 'name' => 'before']);

        $modified = DB::table('gh2030_characters')
            ->where('_id', 'u1')
            ->update(['name' => 'after'], ['writeConcern' => new WriteConcern(0)]);

        $this->assertSame(0, $modified);
        $this->assertSame('after', DB::table('gh2030_characters')->where('_id', 'u1')->value('name'));
    }
}
