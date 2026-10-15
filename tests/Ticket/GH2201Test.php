<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Ticket;

use Illuminate\Database\SQLiteConnection;
use MongoDB\Laravel\Tests\Models\Experience;
use MongoDB\Laravel\Tests\Models\Label;
use MongoDB\Laravel\Tests\Models\Skill;
use MongoDB\Laravel\Tests\Models\SqlUser;
use MongoDB\Laravel\Tests\TestCase;
use PDOException;

use function array_values;
use function assert;

/** @see https://github.com/mongodb/laravel-mongodb/issues/2201 */
class GH2201Test extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        try {
            $connection = SqlUser::query()->getConnection();
            assert($connection instanceof SQLiteConnection);
            $connection->select('SELECT 1');
        } catch (PDOException) {
            $this->markTestSkipped('SQLite connection is not available.');
        }

        SqlUser::executeSchema();
    }

    public function tearDown(): void
    {
        SqlUser::truncate();
        Skill::truncate();
        Label::truncate();
        Experience::truncate();

        parent::tearDown();
    }

    public function testDetachFromSqlModelDoesNotCallPull(): void
    {
        $user = $this->sqlUser('John Doe');
        $other = $this->sqlUser('Maria Doe');
        $laravel = Skill::query()->create(['name' => 'Laravel']);
        $mongodb = Skill::query()->create(['name' => 'MongoDB']);

        $user->skills()->attach($laravel);
        $user->skills()->attach($mongodb);
        $other->skills()->attach($laravel);

        $user->unsetRelation('skills');
        $user->skills()->detach($laravel->id);

        self::assertCount(1, $user->skills);
        self::assertSame([$mongodb->id], $user->skills->pluck('id')->all());

        $fresh = SqlUser::query()->findOrFail($user->id);
        self::assertSame([$mongodb->id], $fresh->skills->pluck('id')->all());

        $laravel->refresh();
        self::assertNotContains($user->id, $this->relatedIds($laravel));
        self::assertContains($other->id, $this->relatedIds($laravel));

        $mongodb->refresh();
        self::assertContains($user->id, $this->relatedIds($mongodb));
    }

    public function testDetachModelInstanceFromSqlModel(): void
    {
        $user = $this->sqlUser('John Doe');
        $skill = Skill::query()->create(['name' => 'Laravel']);
        $user->skills()->attach($skill);

        $user->unsetRelation('skills');
        $user->skills()->detach($skill);

        self::assertCount(0, $user->skills);
        self::assertCount(0, SqlUser::query()->findOrFail($user->id)->skills);

        $skill->refresh();
        self::assertNotContains($user->id, $this->relatedIds($skill));
    }

    public function testDetachAllFromSqlModelClearsLoadedRelation(): void
    {
        $user = $this->sqlUser('John Doe');
        $other = $this->sqlUser('Maria Doe');
        $laravel = Skill::query()->create(['name' => 'Laravel']);
        $mongodb = Skill::query()->create(['name' => 'MongoDB']);

        $user->skills()->attach([$laravel->id, $mongodb->id]);
        $other->skills()->attach($laravel);

        $user->unsetRelation('skills');
        $user->skills()->detach();

        self::assertCount(0, $user->skills);
        self::assertCount(0, SqlUser::query()->findOrFail($user->id)->skills);
        self::assertSame([$laravel->id], SqlUser::query()->findOrFail($other->id)->skills->pluck('id')->all());

        $laravel->refresh();
        $mongodb->refresh();
        self::assertNotContains($user->id, $this->relatedIds($laravel));
        self::assertNotContains($user->id, $this->relatedIds($mongodb));
        self::assertContains($other->id, $this->relatedIds($laravel));
    }

    public function testDetachFromSqlMorphToManyDoesNotCallPull(): void
    {
        $user = $this->sqlUser('John Doe');
        $laravel = Label::query()->create(['name' => 'Laravel']);
        $mongodb = Label::query()->create(['name' => 'MongoDB']);

        $user->labels()->attach([$laravel->id, $mongodb->id]);
        $user->unsetRelation('labels');
        $user->labels()->detach($laravel->id);

        self::assertSame([$mongodb->id], $user->labels->pluck('id')->all());
        self::assertSame([$mongodb->id], SqlUser::query()->findOrFail($user->id)->labels->pluck('id')->all());

        $user->unsetRelation('labels');
        $user->labels()->detach();

        self::assertCount(0, $user->labels);
        self::assertCount(0, SqlUser::query()->findOrFail($user->id)->labels);
    }

    public function testDetachAllFromSqlMorphedByManyClearsLoadedRelation(): void
    {
        $user = $this->sqlUser('John Doe');
        $expert = Experience::query()->create(['title' => 'DB expert']);
        $mongodb = Experience::query()->create(['title' => 'MongoDB']);

        $user->experiences()->attach([$expert->id, $mongodb->id]);
        $user->unsetRelation('experiences');
        $user->experiences()->detach();

        self::assertCount(0, $user->experiences);
        self::assertCount(0, SqlUser::query()->findOrFail($user->id)->experiences);
    }

    private function sqlUser(string $name): SqlUser
    {
        $user = new SqlUser();
        $user->fill(['name' => $name])->save();

        $found = SqlUser::query()->findOrFail($user->id);
        assert($found instanceof SqlUser);

        return $found;
    }

    /** @return list<int|string> */
    private function relatedIds(Skill $skill): array
    {
        return array_values($skill->getAttribute('sql_user_ids') ?? []);
    }
}
