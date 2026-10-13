<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Ticket;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use MongoDB\Laravel\Eloquent\Casts\ObjectId;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Tests\TestCase;

/** @see https://github.com/mongodb/laravel-mongodb/issues/2986 */
class GH2986Test extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        GH2986EntityA::truncate();
        GH2986EntityB::truncate();
    }

    public function tearDown(): void
    {
        DB::connection('mongodb')->disableQueryLog();

        parent::tearDown();
    }

    public function testWhereHasMatchesObjectIdForeignKey(): void
    {
        $added = GH2986EntityA::create(['status' => 'added']);
        $other = GH2986EntityA::create(['status' => 'other']);

        GH2986EntityB::create(['entity_a_id' => $added->id, 'name' => 'match']);
        GH2986EntityB::create(['entity_a_id' => $other->id, 'name' => 'skip']);

        DB::enableQueryLog();

        $found = GH2986EntityB::whereHas('entityA', static fn ($query) => $query->where('status', 'added'))->get();

        $this->assertCount(1, $found);
        $this->assertSame('match', $found->first()->name);

        $logs = DB::getQueryLog();
        $this->assertCount(2, $logs);
        $this->assertStringContainsString('gh2986_entity_a', $logs[0]['query']);
        $this->assertStringContainsString('gh2986_entity_b', $logs[1]['query']);
        $this->assertStringContainsString('$oid', $logs[1]['query']);
        $this->assertStringContainsString((string) $added->id, $logs[1]['query']);
        $this->assertStringNotContainsString((string) $other->id, $logs[1]['query']);
    }

    public function testWhereDoesntHaveExcludesObjectIdMatches(): void
    {
        $added = GH2986EntityA::create(['status' => 'added']);
        $other = GH2986EntityA::create(['status' => 'other']);

        GH2986EntityB::create(['entity_a_id' => $added->id, 'name' => 'match']);
        GH2986EntityB::create(['entity_a_id' => $other->id, 'name' => 'keep']);

        $found = GH2986EntityB::whereDoesntHave('entityA', static fn ($query) => $query->where('status', 'added'))->get();

        $this->assertCount(1, $found);
        $this->assertSame('keep', $found->first()->name);
    }

    public function testHasManyWhereHasStillMatchesParentObjectId(): void
    {
        $added = GH2986EntityA::create(['status' => 'added']);
        $other = GH2986EntityA::create(['status' => 'other']);

        GH2986EntityB::create(['entity_a_id' => $added->id, 'name' => 'match']);
        GH2986EntityB::create(['entity_a_id' => $other->id, 'name' => 'skip']);

        $found = GH2986EntityA::whereHas('entitiesB', static fn ($query) => $query->where('name', 'match'))->get();

        $this->assertCount(1, $found);
        $this->assertSame($added->id, $found->first()->id);
    }
}

class GH2986EntityA extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'gh2986_entity_a';
    protected $guarded = [];

    public function entitiesB(): HasMany
    {
        return $this->hasMany(GH2986EntityB::class, 'entity_a_id');
    }
}

class GH2986EntityB extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'gh2986_entity_b';
    protected $guarded = [];
    protected $casts = [
        'entity_a_id' => ObjectId::class,
    ];

    public function entityA(): BelongsTo
    {
        return $this->belongsTo(GH2986EntityA::class);
    }
}
