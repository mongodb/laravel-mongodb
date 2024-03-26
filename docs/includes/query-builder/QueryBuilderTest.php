<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\Regex;
use MongoDB\Laravel\Collection;
use MongoDB\Laravel\Tests\TestCase;

class QueryBuilderTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testWhere(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin query where
        $result = DB::connection('mongodb')
            ->collection('movies')
            ->where('imdb.rating', 9.3)
            ->get();
        // end query where
    }

    public function testOrWhere(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin query orWhere
        $result = DB::connection('mongodb')
            ->collection('movies')
            ->where('year', 1955)
            ->orWhere('title', 'Back to the Future')
            ->get();
        // end query orWhere
    }

    public function testAndWhere(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin query andWhere
        $result = DB::connection('mongodb')
            ->collection('movies')
            ->where('imdb.rating', '>', 8.5)
            ->where('year', '<', 1940)
            ->get();
        // end query andWhere
    }

    public function testWhereNot(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin query whereNot
        $result = DB::connection('mongodb')
            ->collection('movies')
            ->whereNot('imdb.rating', '>', 2)
            ->get();
        // end query whereNot
    }

    public function testNestedLogical(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin query nestedLogical
        $result = DB::connection('mongodb')
            ->collection('movies')
            ->where('imdb.rating', '>', 8.5)
            ->where(function (Builder $query) {
                return $query
                    ->where('year', 1986)
                    ->orWhere('year', 1996);
            })->get();
        // end query nestedLogical
    }

    public function testWhereBetween(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin query whereBetween
        $result = DB::connection('mongodb')
            ->collection('movies')
            ->whereBetween('imdb.rating', [9, 9.5])
            ->get();
        // end query whereBetween
    }

    public function testWhereNull(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin query whereNull
        $result = DB::connection('mongodb')
            ->collection('movies')
            ->whereNull('runtime')
            ->get();
        // end query whereNull
    }

    public function testWhereIn(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin query whereIn
        $result = DB::collection('movies')
            ->whereIn('title', ['Toy Story', 'Shrek 2', 'Johnny English'])
            ->get();
        // end query whereIn
    }

    public function testWhereDate(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin query whereDate
        $result = DB::connection('mongodb')
            ->collection('movies')
            ->whereDate('released', '2010-1-15')
            ->get();
        // end query whereDate
    }

    public function testLike(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin query like
        $result = DB::collection('movies')
            ->where('title', 'like', '%spider_man%')
            ->get();
        // end query like
    }

    public function testDistinct(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin query distinct
        $result = DB::collection('movies')
            ->distinct('year')->get();
        // end query distinct
    }

    public function testGroupBy(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin query groupBy
        $result = DB::collection('movies')
           ->where('rated', 'G')
           ->groupBy('runtime')
           ->orderBy('runtime', 'asc')
           ->get(['title']);
        // end query groupBy
    }

    public function testAggCount(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin aggregation count
        $result = DB::collection('movies')
            ->count();
        // end aggregation count
    }

    public function testAggMax(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin aggregation max
        $result = DB::collection('movies')
            ->max('runtime');
        // end aggregation max
    }

    public function testAggMin(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin aggregation min
        $result = DB::collection('movies')
            ->min('year');
        // end aggregation min
    }

    public function testAggAvg(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin aggregation avg
        $result = DB::collection('movies')
            ->avg('imdb.rating');
        // end aggregation avg
    }

    public function testAggSum(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin aggregation sum
        $result = DB::collection('movies')
            ->sum('imdb.votes');
        // end aggregation sum
    }

    public function testAggWithFilter(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin aggregation with filter
        $result = DB::collection('movies')
            ->where('year', '>', 2000)
            ->avg('imdb.rating');
        // end aggregation with filter
    }

    public function testOrderBy(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin query orderBy
        $result = DB::collection('movies')
            ->where('title', 'like', 'back to the future%')
            ->orderBy('imdb.rating', 'desc')
            ->get();
        // end query orderBy
    }

    public function testSkip(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin query skip
        $result = DB::collection('movies')
            ->where('title', 'like', 'star trek%')
            ->orderBy('year', 'asc')
            ->skip(4)
            ->get();
        // end query skip
    }

    public function testProjection(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin query projection
        $result = DB::collection('movies')
            ->where('imdb.rating', '>', 8.5)
            ->project([
                'title' => 1,
                'cast' => ['$slice' => [1, 3]],
            ])
            ->get();
        // end query projection
    }

    public function testProjectionWithPagination(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin query projection with pagination
        $resultsPerPage = 15;
        $projectionFields = ['title', 'runtime', 'imdb.rating'];

        $result = DB::collection('movies')
            ->orderBy('imdb.votes', 'desc')
            ->paginate($resultsPerPage, $projectionFields);
        // end query projection with pagination
    }

    public function testExists(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin query exists
        $result = DB::collection('movies')
            ->exists('random_review', true);
        // end query exists
    }

    public function testAll(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin query all
        $result = DB::collection('movies')
            ->where('movies', 'all', ['title', 'rated', 'imdb.rating'])
            ->get();
        // end query all
    }

    public function testSize(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin query size
        $result = DB::collection('movies')
            ->where('directors', 'size', 5)
            ->get();
        // end query size
    }

    public function testType(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin query type
        $result = DB::collection('movies')
            ->where('released', 'type', 4)
            ->get();
        // end query type
    }

    public function testMod(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin query modulo
        $result = DB::collection('movies')
            ->where('year', 'mod', [2, 0])
            ->get();
        // end query modulo
    }

    public function testWhereRegex(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin query whereRegex
        $result = DB::connection('mongodb')
            ->collection('movies')
            ->where('title', 'REGEX', new Regex('^the lord of .*', 'i'))
            ->get();
        // end query whereRegex
    }

    public function testWhereRaw(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin query raw
        $result = DB::collection('movies')
            ->whereRaw([
                'imdb.votes' => ['$gte' => 1000 ],
                '$or' => [
                    ['imdb.rating' => ['$gt' => 7]],
                    ['directors' => ['$in' => [ 'Yasujiro Ozu', 'Sofia Coppola', 'Federico Fellini' ]]],
                ],
            ])->get();
        // end query raw
    }

    public function testElemMatch(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin query elemMatch
        $result = DB::collection('movies')
            ->where('writers', 'elemMatch', ['$in' => ['Maya Forbes', 'Eric Roth']])
            ->get();
        // end query elemMatch
    }

    public function testCursorTimeout(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin query cursor timeout
        $result = DB::collection('movies')
            ->timeout(2) // value in seconds
            ->where('year', 2001)
            ->get();
        // end query cursor timeout
    }

    public function testNear(): void
    {
        // <optionally, add code here to clean the database/collection>
        // begin query near
        $results = DB::collection('theaters')
            ->where('location.geo', 'near', [
                '$geometry' => [
                    'type' => 'Point',
                    'coordinates' => [
                        -86.6423,
                        33.6054,
                    ],
                ],
                '$maxDistance' => 50,
            ])->get();
        // end query near
    }

    public function testGeoWithin(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin query geoWithin
        $results = DB::collection('theaters')
            ->where('location.geo', 'geoWithin', [
                '$geometry' => [
                    'type' => 'Polygon',
                    'coordinates' => [
                        [
                            [-72, 40],
                            [-74, 41],
                            [-72, 39],
                            [-72, 40],
                        ],
                    ],
                ],
            ])->get();
        // end query geoWithin
    }

    public function testGeoIntersects(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin query geoIntersects
        $results = DB::collection('theaters')
            ->where('location.geo', 'geoIntersects', [
                '$geometry' => [
                    'type' => 'LineString',
                    'coordinates' => [
                        [-73.600525, 40.74416],
                        [-72.600525, 40.74416],
                    ],
                ],
            ])->get();
        // end query geoIntersects
    }

    public function testGeoNear(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin query geoNear
        $results = DB::collection('theaters')->raw(
            function (Collection $collection) {
                return $collection->aggregate([
                    [
                        '$geoNear' => [
                            'near' => [
                                'type' => 'Point',
                                'coordinates' => [-118.34, 34.10],
                            ],
                            'distanceField' => 'dist.calculated',
                            'maxDistance' => 500,
                            'includeLocs' => 'dist.location',
                            'spherical' => true,
                        ],
                    ],
                ]);
            },
        );
        // end query geoNear
    }

    public function testUpsert(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin upsert
        $result = DB::collection('movies')
            ->where('title', 'Will Hunting')
            ->update(
                [
                    'plot' => 'An autobiographical movie',
                    'year' => 1998,
                    'writers' => [ 'Will Hunting' ],
                ],
                ['upsert' => true],
            );
        // end upsert
    }

    public function testIncrement(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin increment
        $result = DB::collection('movies')
            ->where('title', 'Field of Dreams')
            ->increment('imdb.votes', 3000);
        // end increment
    }

    public function testDecrement(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin decrement
        $result = DB::collection('movies')
            ->where('title', 'Sharknado')
            ->decrement('imdb.rating', 0.2);
        // end decrement
    }

    public function testPush(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin push
        $result = DB::collection('movies')
            ->where('title', 'Office Space')
            ->push('cast', 'Gary Cole');
        // end push
    }

    public function testPull(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin pull
        $result = DB::collection('movies')
            ->where('title', 'Iron Man')
            ->pull('genres', 'Adventure');
        // end pull
    }

    public function testUnset(): void
    {
        // <optionally, add code here to clean the database/collection>

        // begin unset
        $result = DB::collection('movies')
            ->where('title', 'Final Accord')
            ->unset('tomatoes.viewer');
        // end unset
    }
}
