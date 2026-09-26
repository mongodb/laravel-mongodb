<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Ticket;

use MongoDB\BSON\ObjectId;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsToMany;
use MongoDB\Laravel\Tests\TestCase;

use function array_map;
use function sort;

/**
 * BelongsToMany with related keys exposed as ObjectId instances (instead of strings) must
 * store the ids as BSON ObjectId in the document, not as their exploded ["oid" => ...] array.
 *
 * @see https://github.com/mongodb/laravel-mongodb/issues/3015
 * @see https://jira.mongodb.org/browse/PHPORM-116
 */
class GH3015Test extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        GH3015Planet::truncate();
        GH3015SpaceExplorer::truncate();
    }

    public function testAttachModelWithObjectIdKeyStoresObjectId(): void
    {
        $earth = GH3015Planet::create(['name' => 'Earth']);
        $jupiter = GH3015Planet::create(['name' => 'Jupiter']);
        $tanya = GH3015SpaceExplorer::create(['name' => 'Tanya Kirbuk']);

        $this->assertInstanceOf(ObjectId::class, $tanya->getKey());
        $this->assertInstanceOf(ObjectId::class, $earth->getKey());

        $tanya->planetsVisited()->attach($earth);
        $tanya->planetsVisited()->attach($jupiter);

        // Check the BSON type in the stored documents on both sides of the relation
        $document = $this->findRaw(GH3015SpaceExplorer::class, $tanya->getKey());
        $this->assertCount(2, $document['planetIds']);
        $this->assertContainsOnlyInstancesOf(ObjectId::class, $document['planetIds']);
        $this->assertEquals([$earth->getKey(), $jupiter->getKey()], $document['planetIds']);

        $document = $this->findRaw(GH3015Planet::class, $earth->getKey());
        $this->assertEquals([$tanya->getKey()], $document['visitorIds']);
        $this->assertContainsOnlyInstancesOf(ObjectId::class, $document['visitorIds']);

        // The in-memory attribute of the parent is updated with the same type
        $this->assertContainsOnlyInstancesOf(ObjectId::class, $tanya->planetIds);
        $this->assertCount(2, $tanya->planetIds);
    }

    public function testLoadRelationsWithObjectIdKeys(): void
    {
        $earth = GH3015Planet::create(['name' => 'Earth']);
        $jupiter = GH3015Planet::create(['name' => 'Jupiter']);
        $mars = GH3015Planet::create(['name' => 'Mars']);
        $tanya = GH3015SpaceExplorer::create(['name' => 'Tanya Kirbuk']);
        $john = GH3015SpaceExplorer::create(['name' => 'John Crichton']);

        $tanya->planetsVisited()->attach([$earth->getKey(), $jupiter->getKey()]);
        $john->planetsVisited()->attach($mars);

        // Lazy loading
        $this->assertSame(['Earth', 'Jupiter'], $this->names(GH3015SpaceExplorer::find($tanya->getKey())->planetsVisited));
        $this->assertSame(['Mars'], $this->names(GH3015SpaceExplorer::find($john->getKey())->planetsVisited));

        // Eager loading builds a dictionary keyed by the stored ObjectId values
        $explorers = GH3015SpaceExplorer::with('planetsVisited')->orderBy('name')->get();
        $this->assertTrue($explorers[0]->relationLoaded('planetsVisited'));
        $this->assertSame(['Mars'], $this->names($explorers[0]->planetsVisited));
        $this->assertSame(['Earth', 'Jupiter'], $this->names($explorers[1]->planetsVisited));

        // Inverse side: lazy and eager loading
        $this->assertSame(['Tanya Kirbuk'], $this->names(GH3015Planet::find($earth->getKey())->visitors));

        $planets = GH3015Planet::with('visitors')->orderBy('name')->get();
        $this->assertSame(['Tanya Kirbuk'], $this->names($planets[0]->visitors)); // Earth
        $this->assertSame(['Tanya Kirbuk'], $this->names($planets[1]->visitors)); // Jupiter
        $this->assertSame(['John Crichton'], $this->names($planets[2]->visitors)); // Mars
    }

    public function testDetachModelWithObjectIdKey(): void
    {
        $earth = GH3015Planet::create(['name' => 'Earth']);
        $jupiter = GH3015Planet::create(['name' => 'Jupiter']);
        $tanya = GH3015SpaceExplorer::create(['name' => 'Tanya Kirbuk']);

        $tanya->planetsVisited()->attach([$earth->getKey(), $jupiter->getKey()]);

        $this->assertSame(1, $tanya->planetsVisited()->detach($earth));

        $document = $this->findRaw(GH3015SpaceExplorer::class, $tanya->getKey());
        $this->assertEquals([$jupiter->getKey()], $document['planetIds']);
        $this->assertSame(['Jupiter'], $this->names($tanya->planetsVisited()->get()));

        $document = $this->findRaw(GH3015Planet::class, $earth->getKey());
        $this->assertSame([], $document['visitorIds']);

        // Detach an ObjectId instance
        $tanya->planetsVisited()->detach($jupiter->getKey());
        $document = $this->findRaw(GH3015SpaceExplorer::class, $tanya->getKey());
        $this->assertSame([], $document['planetIds']);
    }

    public function testSyncWithObjectIdKeys(): void
    {
        $earth = GH3015Planet::create(['name' => 'Earth']);
        $jupiter = GH3015Planet::create(['name' => 'Jupiter']);
        $mars = GH3015Planet::create(['name' => 'Mars']);
        $tanya = GH3015SpaceExplorer::create(['name' => 'Tanya Kirbuk']);

        // Sync a list of ObjectId instances
        $changes = $tanya->planetsVisited()->sync([$earth->getKey(), $jupiter->getKey()]);
        $this->assertSame([(string) $earth->getKey(), (string) $jupiter->getKey()], $changes['attached']);
        $this->assertSame([], $changes['detached']);

        $document = $this->findRaw(GH3015SpaceExplorer::class, $tanya->getKey());
        $this->assertEquals([$earth->getKey(), $jupiter->getKey()], $document['planetIds']);
        $this->assertContainsOnlyInstancesOf(ObjectId::class, $document['planetIds']);

        // Sync again: the already attached ObjectId is left untouched, one is detached, one attached
        $tanya = GH3015SpaceExplorer::find($tanya->getKey());
        $changes = $tanya->planetsVisited()->sync([$jupiter->getKey(), $mars->getKey()]);
        $this->assertSame([(string) $mars->getKey()], $changes['attached']);
        $this->assertSame([(string) $earth->getKey()], $changes['detached']);

        $document = $this->findRaw(GH3015SpaceExplorer::class, $tanya->getKey());
        $this->assertEquals([$jupiter->getKey(), $mars->getKey()], $document['planetIds']);
        $this->assertSame(['Jupiter', 'Mars'], $this->names($tanya->planetsVisited()->get()));
        $this->assertSame([], $this->findRaw(GH3015Planet::class, $earth->getKey())['visitorIds']);

        // Sync an Eloquent collection of models with ObjectId keys
        $tanya = GH3015SpaceExplorer::find($tanya->getKey());
        $changes = $tanya->planetsVisited()->sync(GH3015Planet::whereIn('name', ['Earth'])->get());
        $this->assertSame([(string) $earth->getKey()], $changes['attached']);
        $detached = $changes['detached'];
        sort($detached);
        $expected = [(string) $jupiter->getKey(), (string) $mars->getKey()];
        sort($expected);
        $this->assertSame($expected, $detached);
        $this->assertSame(['Earth'], $this->names($tanya->planetsVisited()->get()));

        // Sync without detaching
        $changes = $tanya->planetsVisited()->syncWithoutDetaching([$earth->getKey(), $mars->getKey()]);
        $this->assertSame([(string) $mars->getKey()], $changes['attached']);
        $this->assertSame([], $changes['detached']);
        $this->assertSame(['Earth', 'Mars'], $this->names($tanya->planetsVisited()->get()));
    }

    /** @return list<string> */
    private function names(iterable $models): array
    {
        $names = array_map(static fn (Model $model) => $model->name, [...$models]);
        sort($names);

        return $names;
    }

    /**
     * Read the stored document as plain PHP arrays to assert on the BSON types of its values.
     *
     * @param class-string<Model> $model
     */
    private function findRaw(string $model, ObjectId $id): array
    {
        return $model::raw()->findOne(
            ['_id' => $id],
            ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']],
        );
    }
}

/** Exposes the primary key as an ObjectId instance instead of a string */
class GH3015Planet extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'gh3015_planets';
    protected static $unguarded = true;

    public function getIdAttribute($value = null)
    {
        return $value ?? $this->attributes['_id'] ?? null;
    }

    public function visitors(): BelongsToMany
    {
        return $this->belongsToMany(GH3015SpaceExplorer::class, null, 'planetIds', 'visitorIds');
    }
}

/** Exposes the primary key as an ObjectId instance instead of a string */
class GH3015SpaceExplorer extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'gh3015_space_explorers';
    protected static $unguarded = true;

    public function getIdAttribute($value = null)
    {
        return $value ?? $this->attributes['_id'] ?? null;
    }

    public function planetsVisited(): BelongsToMany
    {
        return $this->belongsToMany(GH3015Planet::class, null, 'visitorIds', 'planetIds');
    }
}
