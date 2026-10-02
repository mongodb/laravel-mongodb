<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use MongoDB\BSON\ObjectId;
use MongoDB\Laravel\Relations\HasManyThrough;
use MongoDB\Laravel\Relations\HasOneThrough;
use MongoDB\Laravel\Tests\Models\Director;
use MongoDB\Laravel\Tests\Models\Film;
use MongoDB\Laravel\Tests\Models\Studio;

use function collect;

class ThroughRelationsTest extends TestCase
{
    public function tearDown(): void
    {
        Studio::truncate();
        Director::truncate();
        Film::truncate();

        parent::tearDown();
    }

    public function testHasManyThrough(): void
    {
        $studio = Studio::create(['name' => 'A24']);
        $aster = $studio->directors()->create(['name' => 'Ari Aster']);
        $aster->films()->create(['title' => 'Hereditary']);
        $aster->films()->create(['title' => 'Midsommar']);
        $studio->directors()->create(['name' => 'Robert Eggers'])->films()->create(['title' => 'The Lighthouse']);

        $other = Studio::create(['name' => 'Blumhouse']);
        $other->directors()->create(['name' => 'Jordan Peele'])->films()->create(['title' => 'Get Out']);

        self::assertSame(
            ['Hereditary', 'Midsommar', 'The Lighthouse'],
            $studio->films->pluck('title')->sort()->values()->all(),
        );
        self::assertSame(['Get Out'], $other->films->pluck('title')->all());
    }

    public function testHasManyThroughWithoutRelatedDocuments(): void
    {
        $studio = Studio::create(['name' => 'A24']);
        $studio->directors()->create(['name' => 'Ari Aster']);

        self::assertInstanceOf(Collection::class, $studio->films);
        self::assertCount(0, $studio->films);
    }

    public function testHasManyThroughWithoutThroughDocuments(): void
    {
        $studio = Studio::create(['name' => 'A24']);
        Director::create(['name' => 'Jordan Peele'])->films()->create(['title' => 'Get Out']);

        self::assertCount(0, $studio->films);
    }

    public function testHasOneThrough(): void
    {
        $studio = Studio::create(['name' => 'A24']);
        $studio->directors()->create(['name' => 'Ari Aster'])->films()->create(['title' => 'Hereditary']);

        self::assertInstanceOf(Film::class, $studio->firstFilm);
        self::assertSame('Hereditary', $studio->firstFilm->title);
    }

    public function testHasOneThroughWithoutRelatedDocuments(): void
    {
        $studio = Studio::create(['name' => 'A24']);
        $studio->directors()->create(['name' => 'Ari Aster']);

        self::assertNull($studio->firstFilm);
    }

    public function testHasManyThroughNarrowedToOne(): void
    {
        $studio = Studio::create(['name' => 'A24']);
        $studio->directors()->create(['name' => 'Ari Aster'])->films()->create(['title' => 'Hereditary']);

        $other = Studio::create(['name' => 'Blumhouse']);
        $other->directors()->create(['name' => 'Jordan Peele'])->films()->create(['title' => 'Get Out']);

        $relation = $studio->films()->one();

        self::assertInstanceOf(HasOneThrough::class, $relation);
        self::assertSame('Hereditary', $relation->getResults()->title);
        self::assertSame('Get Out', $other->films()->one()->getResults()->title);
    }

    public function testHasManyThroughNarrowedToOneKeepsCallerConstraints(): void
    {
        $studio = Studio::create(['name' => 'A24']);
        $aster = $studio->directors()->create(['name' => 'Ari Aster']);
        $aster->films()->create(['title' => 'Hereditary']);
        $aster->films()->create(['title' => 'Midsommar']);

        self::assertSame(
            'Midsommar',
            $studio->films()->where('title', 'Midsommar')->one()->getResults()->title,
        );
    }

    public function testHasManyThroughNarrowedToOneWithoutRelatedDocuments(): void
    {
        $studio = Studio::create(['name' => 'A24']);
        $studio->directors()->create(['name' => 'Ari Aster']);

        Studio::create(['name' => 'Blumhouse'])
            ->directors()->create(['name' => 'Jordan Peele'])
            ->films()->create(['title' => 'Get Out']);

        self::assertNull($studio->films()->one()->getResults());
    }

    public function testThroughRelationCannotBuildAnExistenceQuery(): void
    {
        $studio = Studio::create(['name' => 'A24']);
        $studio->directors()->create(['name' => 'Ari Aster'])->films()->create(['title' => 'Hereditary']);

        $relation = Relation::noConstraints(static fn () => $studio->films());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'Through relations cannot be used in existence queries: Eloquent builds the constraint from a join, which MongoDB does not have. Use has() or whereHas() instead.',
        );

        $relation->getRelationExistenceQuery($relation->getRelated()->newQuery(), Studio::query());
    }

    public function testThroughRelationDoesNotSupportOneOfMany(): void
    {
        $studio = Studio::create(['name' => 'A24']);
        $aster = $studio->directors()->create(['name' => 'Ari Aster']);
        $aster->films()->create(['title' => 'Hereditary']);
        $aster->films()->create(['title' => 'Midsommar']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'Through relations do not support ofMany() and latestOfMany(): selecting a single related document relies on a join, which MongoDB does not have.',
        );

        $studio->firstFilm()->latestOfMany();
    }

    public function testHas(): void
    {
        $this->createStudios();

        self::assertSame(['A24', 'Blumhouse'], Studio::has('films')->orderBy('name')->pluck('name')->all());
        self::assertSame(['Neon'], Studio::doesntHave('films')->pluck('name')->all());
        self::assertSame(['A24'], Studio::has('films', '>', 1)->pluck('name')->all());
        self::assertSame(['A24', 'Blumhouse'], Studio::has('firstFilm')->orderBy('name')->pluck('name')->all());
    }

    public function testHasCountsKeysOfDifferentBsonTypesApart(): void
    {
        Studio::create(['name' => 'IntStudio', 'cstudio_id' => 1]);
        Studio::create(['name' => 'StringStudio', 'cstudio_id' => '1']);

        Director::create(['name' => 'Ari Aster', 'cdirector_id' => 10, 'cstudio_ref' => 1]);
        Director::create(['name' => 'Jordan Peele', 'cdirector_id' => '10', 'cstudio_ref' => '1']);

        Film::create(['title' => 'Hereditary', 'cdirector_ref' => 10]);
        Film::create(['title' => 'Get Out', 'cdirector_ref' => '10']);

        self::assertSame(
            ['IntStudio', 'StringStudio'],
            Studio::has('filmsWithCustomKeys')->orderBy('name')->pluck('name')->all(),
        );
        self::assertSame(
            ['IntStudio', 'StringStudio'],
            Studio::has('filmsWithCustomKeys', '=', 1)->orderBy('name')->pluck('name')->all(),
        );
        self::assertSame([], Studio::has('filmsWithCustomKeys', '>', 1)->pluck('name')->all());
    }

    public function testHasLooksUpEachThroughDocumentOnce(): void
    {
        $studio = Studio::create(['name' => 'A24']);
        $director = $studio->directors()->create(['name' => 'Ari Aster']);
        $director->films()->create(['title' => 'Hereditary']);
        $director->films()->create(['title' => 'Midsommar']);
        $director->films()->create(['title' => 'The Lighthouse']);

        $connection = DB::connection('mongodb');
        $connection->enableQueryLog();

        self::assertCount(1, Studio::has('films')->get());

        $throughQueries = collect($connection->getQueryLog())
            ->filter(static fn (array $query): bool => Str::contains($query['query'], '"find" : "directors"'))
            ->pluck('query');

        self::assertCount(1, $throughQueries);
        self::assertSame(1, Str::substrCount($throughQueries->first(), (string) $director->getKey()));
    }

    public function testWhereHas(): void
    {
        $this->createStudios();

        $studios = Studio::whereHas('films', static fn (Builder $query) => $query->where('title', 'Get Out'))->get();

        self::assertSame(['Blumhouse'], $studios->pluck('name')->all());
    }

    public function testNestedHas(): void
    {
        $this->createStudios();

        self::assertSame(
            ['A24', 'Blumhouse'],
            Studio::has('directors.films')->orderBy('name')->pluck('name')->all(),
        );
    }

    public function testConstraintsOnTheRelatedQuery(): void
    {
        $studio = Studio::create(['name' => 'A24']);
        $director = $studio->directors()->create(['name' => 'Ari Aster']);
        $director->films()->create(['title' => 'Hereditary', 'budget' => 10]);
        $director->films()->create(['title' => 'Midsommar', 'budget' => 9]);

        self::assertSame(['Hereditary'], $studio->films()->where('budget', '>', 9)->pluck('title')->all());
        self::assertSame(2, $studio->films()->count());
    }

    public function testSelectingColumnsKeepsTheKeyMatchingTheFarParent(): void
    {
        $this->createStudios();

        $loaded = Studio::with(['films' => static fn (HasManyThrough $relation) => $relation->select('title')])
            ->orderBy('name')
            ->get();

        self::assertSame(
            ['Hereditary', 'Midsommar', 'The Lighthouse'],
            $loaded[0]->films->pluck('title')->sort()->values()->all(),
        );
        self::assertSame(['Get Out'], $loaded[1]->films->pluck('title')->all());
    }

    public function testEagerLoadingHasManyThroughUsesOneQueryPerCollection(): void
    {
        $studios = $this->createStudios();

        $connection = DB::connection('mongodb');
        $connection->enableQueryLog();

        $loaded = Studio::with('films')->orderBy('name')->get();

        self::assertCount(3, $connection->getQueryLog());

        self::assertSame(['A24', 'Blumhouse', 'Neon'], $loaded->pluck('name')->all());
        self::assertSame($studios['a24']->id, $loaded[0]->id);
        self::assertSame(
            ['Hereditary', 'Midsommar', 'The Lighthouse'],
            $loaded[0]->films->pluck('title')->sort()->values()->all(),
        );
        self::assertSame(['Get Out'], $loaded[1]->films->pluck('title')->all());
        self::assertCount(0, $loaded[2]->films);
    }

    public function testEagerLoadingHasOneThroughUsesOneQueryPerCollection(): void
    {
        $this->createStudios();

        $connection = DB::connection('mongodb');
        $connection->enableQueryLog();

        $loaded = Studio::with('firstFilm')->orderBy('name')->get();

        self::assertCount(3, $connection->getQueryLog());

        self::assertInstanceOf(Film::class, $loaded[0]->firstFilm);
        self::assertSame('Get Out', $loaded[1]->firstFilm->title);
        self::assertNull($loaded[2]->firstFilm);
    }

    public function testWithCount(): void
    {
        $this->createStudios();

        $studios = Studio::withCount('films')->withExists('films')->orderBy('name')->get();

        self::assertSame(3, $studios[0]->films_count);
        self::assertTrue($studios[0]->films_exists);
        self::assertSame(1, $studios[1]->films_count);
        self::assertSame(0, $studios[2]->films_count);
        self::assertFalse($studios[2]->films_exists);
    }

    public function testWithCountHasOneThrough(): void
    {
        $this->createStudios();

        $studios = Studio::withCount('firstFilm')->orderBy('name')->get();

        self::assertSame(3, $studios[0]->first_film_count);
        self::assertSame(1, $studios[1]->first_film_count);
        self::assertSame(0, $studios[2]->first_film_count);
    }

    public function testWithAggregateHasOneThrough(): void
    {
        $studio = Studio::create(['name' => 'A24']);
        $aster = $studio->directors()->create(['name' => 'Ari Aster']);
        $eggers = $studio->directors()->create(['name' => 'Robert Eggers']);

        $aster->films()->create(['title' => 'Hereditary', 'budget' => 10]);
        $aster->films()->create(['title' => 'Midsommar', 'budget' => 9]);
        $eggers->films()->create(['title' => 'The Lighthouse', 'budget' => 11]);

        $loaded = Studio::withCount('firstFilm')
            ->withMax('firstFilm', 'budget')
            ->withSum('firstFilm', 'budget')
            ->first();

        self::assertSame(3, $loaded->first_film_count);
        self::assertSame(11, $loaded->first_film_max_budget);
        self::assertSame(30, $loaded->first_film_sum_budget);
    }

    public function testLoadingHasOneThroughStillNarrowsToOneDocument(): void
    {
        $studio = Studio::create(['name' => 'A24']);
        $aster = $studio->directors()->create(['name' => 'Ari Aster']);
        $eggers = $studio->directors()->create(['name' => 'Robert Eggers']);

        $aster->films()->create(['title' => 'Hereditary']);
        $eggers->films()->create(['title' => 'The Lighthouse']);

        self::assertSame('Hereditary', Studio::with('firstFilm')->first()->firstFilm->title);
        self::assertCount(2, Studio::with('films')->first()->films);
    }

    public function testWithAggregate(): void
    {
        $studio = Studio::create(['name' => 'A24']);
        $director = $studio->directors()->create(['name' => 'Ari Aster']);
        $director->films()->create(['title' => 'Hereditary', 'budget' => 10]);
        $director->films()->create(['title' => 'Midsommar', 'budget' => 9]);

        $loaded = Studio::withMax('films', 'budget')->withSum('films', 'budget')->first();

        self::assertSame(10, $loaded->films_max_budget);
        self::assertSame(19, $loaded->films_sum_budget);
    }

    public function testCustomKeyNames(): void
    {
        $studio = Studio::create(['name' => 'A24', 'cstudio_id' => 'studio-a24']);
        Studio::create(['name' => 'Neon', 'cstudio_id' => 'studio-neon']);

        Director::create(['name' => 'Ari Aster', 'cdirector_id' => 'd-aster', 'cstudio_ref' => 'studio-a24']);
        Director::create(['name' => 'Jordan Peele', 'cdirector_id' => 'd-peele', 'cstudio_ref' => 'studio-neon']);

        Film::create(['title' => 'Hereditary', 'cdirector_ref' => 'd-aster']);
        Film::create(['title' => 'Midsommar', 'cdirector_ref' => 'd-aster']);
        Film::create(['title' => 'Get Out', 'cdirector_ref' => 'd-peele']);

        self::assertSame(
            ['Hereditary', 'Midsommar'],
            $studio->filmsWithCustomKeys->pluck('title')->sort()->values()->all(),
        );
        self::assertSame('Hereditary', $studio->firstFilmWithCustomKeys->title);

        $loaded = Studio::with('filmsWithCustomKeys')->orderBy('name')->get();
        self::assertCount(2, $loaded[0]->filmsWithCustomKeys);
        self::assertSame(['Get Out'], $loaded[1]->filmsWithCustomKeys->pluck('title')->all());

        $counted = Studio::withCount('filmsWithCustomKeys')->orderBy('name')->get();
        self::assertSame(2, $counted[0]->films_with_custom_keys_count);
        self::assertSame(1, $counted[1]->films_with_custom_keys_count);

        self::assertSame(['A24', 'Neon'], Studio::has('filmsWithCustomKeys')->orderBy('name')->pluck('name')->all());
    }

    public function testCustomKeysOfDifferentBsonTypesDoNotShareDocuments(): void
    {
        Studio::create(['name' => 'IntStudio', 'cstudio_id' => 1]);
        Studio::create(['name' => 'StringStudio', 'cstudio_id' => '1']);

        Director::create(['name' => 'Ari Aster', 'cdirector_id' => 10, 'cstudio_ref' => 1]);
        Director::create(['name' => 'Jordan Peele', 'cdirector_id' => '10', 'cstudio_ref' => '1']);

        Film::create(['title' => 'Hereditary', 'cdirector_ref' => 10]);
        Film::create(['title' => 'Get Out', 'cdirector_ref' => '10']);

        $loaded = Studio::with('filmsWithCustomKeys')->orderBy('name')->get();

        self::assertSame(['Hereditary'], $loaded[0]->filmsWithCustomKeys->pluck('title')->all());
        self::assertSame(['Get Out'], $loaded[1]->filmsWithCustomKeys->pluck('title')->all());
    }

    public function testCustomKeysOfObjectIdAndStringDoNotShareDocuments(): void
    {
        $id = new ObjectId();

        Studio::create(['name' => 'ObjectIdStudio', 'cstudio_id' => $id]);
        Studio::create(['name' => 'StringStudio', 'cstudio_id' => (string) $id]);

        Director::create(['name' => 'Ari Aster', 'cdirector_id' => 10, 'cstudio_ref' => $id]);
        Director::create(['name' => 'Jordan Peele', 'cdirector_id' => '10', 'cstudio_ref' => (string) $id]);

        Film::create(['title' => 'Hereditary', 'cdirector_ref' => 10]);
        Film::create(['title' => 'Get Out', 'cdirector_ref' => '10']);

        $loaded = Studio::with('filmsWithCustomKeys')->orderBy('name')->get();

        self::assertSame(['Hereditary'], $loaded[0]->filmsWithCustomKeys->pluck('title')->all());
        self::assertSame(['Get Out'], $loaded[1]->filmsWithCustomKeys->pluck('title')->all());
    }

    /** @return array<string, Studio> */
    private function createStudios(): array
    {
        $a24 = Studio::create(['name' => 'A24']);
        $aster = $a24->directors()->create(['name' => 'Ari Aster']);
        $aster->films()->create(['title' => 'Hereditary']);
        $aster->films()->create(['title' => 'Midsommar']);
        $a24->directors()->create(['name' => 'Robert Eggers'])->films()->create(['title' => 'The Lighthouse']);

        $blumhouse = Studio::create(['name' => 'Blumhouse']);
        $blumhouse->directors()->create(['name' => 'Jordan Peele'])->films()->create(['title' => 'Get Out']);

        $neon = Studio::create(['name' => 'Neon']);
        $neon->directors()->create(['name' => 'Bong Joon-ho']);

        return ['a24' => $a24, 'blumhouse' => $blumhouse, 'neon' => $neon];
    }
}
