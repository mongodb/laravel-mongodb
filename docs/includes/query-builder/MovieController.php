<?php

namespace App\Http\Controllers;

use App\Models\Movie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\Regex;
use MongoDB\Laravel\Query\Builder;
class MovieController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index() {}

    /**
     * Show the form for creating a new resource.
     */
    public function create() {}

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {}


    private function runWhere() {
        // begin query where
        $movies = DB::connection('mongodb')
            ->collection('movies')
            ->where('imdb.rating', 9.3)
            ->get();
        // end query where

        return $movies;
    }

    private function runOrWhere() {
        // begin query orWhere
        $movies = DB::connection('mongodb')
            ->collection('movies')
            ->where('year', 1955)
            ->orWhere('title', 'Back to the Future')
            ->get();
        // end query orWhere

        return $movies;
    }
    private function runAndWhere() {
        // begin query andWhere
        $movies = DB::connection('mongodb')
            ->collection('movies')
            ->where('imdb.rating', '>', 8.5)
            ->where('year', '<', 1940)
            ->get();
        // end query andWhere

        return $movies;
    }
    private function runNestedLogical() {
        // begin query nestedLogical
        $movies = DB::connection('mongodb')
            ->collection('movies')
            ->where('imdb.rating', '>', 8.5)
            ->where(function ($query) {
                return $query
                    ->where('year', 1986)
                    ->orWhere('year', 1996);
            })->get();
        // end query nestedLogical

        return $movies;
    }
    private function runWhereNot() {
        // begin query whereNot
        $movies = DB::connection('mongodb')
            ->collection('movies')
            ->whereNot('imdb.rating', '>', 2)
            ->get();
        // end query whereNot

        return $movies;
    }

    private function runWhereBetween() {
        // begin query whereBetween
        $movies = DB::connection('mongodb')
            ->collection('movies')
            ->whereBetween('imdb.rating', [9, 9.5])
            ->get();
        // end query whereBetween

        return $movies;
    }

    private function runWhereNull() {
        // begin query whereNull
        $movies = DB::connection('mongodb')
            ->collection('movies')
            ->whereNull('runtime')
            ->get();
        // end query whereNull

        return $movies;
    }

    private function runWhereDate() {
        // begin query whereDate
        $movies = DB::connection('mongodb')
            ->collection('movies')
            ->whereDate('released', '2010-1-15')
            ->get();
        // end query whereDate

        return $movies;
    }

    private function runRegex() {
        // begin query whereRegex
        $movies = DB::connection('mongodb')
            ->collection('movies')
            ->where('title', 'REGEX', new Regex('^the lord of .*', 'i' ))
            ->get();
        // end query whereRegex

        return $movies;
    }


    private function runWhereIn() {
        // begin query whereIn
        $movies = DB::collection('movies')
            ->whereIn('title', ['Toy Story', 'Shrek 2', 'Johnny English'])
            ->get();
        // end query whereIn

        return $movies;
    }

    private function runLike() {
        // begin query like
        $movies = DB::collection('movies')
            ->where('title', 'like', '%spider%man%')
            ->get();
        // end query like

        return $movies;
    }

    private function runExists() {
        // begin query exists
        $result = DB::collection('movies')
            ->exists('title', 'I\'m a Cyborg, But That\'s OK');
        // end query exists

        print_r($result);
        return null;
    }


    private function runAll() {
        // begin query all
        $movies = DB::collection('movies')
            ->where('movies', 'all', ['title', 'rated', 'imdb.rating' ])
            ->getl();
        // end query all

        return $movies;
    }


    private function runSize() {
        // begin query size
        $result = DB::collection('movies')
            ->where('directors', 'size', 5)
            ->get();
        // end query size

        print_r($result);
        return null;
    }

    private function runType() {
        // begin query type
        $movies = DB::collection('movies')
            ->where('released', 'type', 4)
            ->get();
        // end query type

        return $movies;
    }


    private function runMod() {
        // begin query modulo
        $movies = DB::collection('movies')
            ->where('year', 'mod', [2, 0])
            ->get();
        // end query modulo

        return $movies;
    }

    private function runDistinct() {
        // begin query distinct
        $result = DB::collection('movies')
            ->distinct('year')->get();
        // end query distinct
        print_r($result);
        return null;
    }

    private function runWhereDistinct() {
        // begin query where distinct
        $result = DB::collection('movies')
            ->where('imdb.rating', '>', 9)
            ->distinct('year')
            ->get();
        // end query where distinct
        print_r($result);
        return null;
     }

     private function runOrderBy() {
        // begin query orderBy
        $movies = DB::collection('movies')
        ->where('title', 'like', 'back to the future%')
        ->orderBy('imdb.rating', 'desc')
        ->get();
        // end query orderBy

        return $movies;
     }

     private function runGroupBy() {
         // begin query groupBy
        $result = DB::collection('movies')
        ->groupBy('year')
        ->get(['title']);
        // end query groupBy

        print_r($result);
        return null;
     }

     private function runAggCount() {
        // begin aggregation count
       $result = DB::collection('movies')
       ->count();
       // end aggregation count

       print_r($result);
       return null;
    }

    private function runAggMax() {
        // begin aggregation max
       $result = DB::collection('movies')
       ->max('runtime');
       // end aggregation max

       print_r($result);
       return null;
    }

    private function runAggMin() {
        // begin aggregation min
       $result = DB::collection('movies')
       ->min('year');
       // end aggregation min

       print_r($result);
       return null;
    }

    private function runAggAvg() {
        // begin aggregation avg
       $result = DB::collection('movies')
       ->avg('imdb.rating');
       // end aggregation avg

       print_r($result);
       return null;
    }

    private function runAggSum() {
        // begin aggregation sum
       $result = DB::collection('movies')
       ->sum('imdb.votes');
       // end aggregation sum

       print_r($result);
       return null;
    }

    private function runAggWithFilter() {
        // begin aggregation with filter
       $result = DB::collection('movies')
       ->where('year', '>', 2000)
       ->avg('imdb.rating');
       // end aggregation with filter

       print_r($result);
       return null;
    }

     private function runSkip() {
        // begin query skip
        $movies = DB::collection('movies')
            ->where('title', 'like', 'star trek%')
            ->orderBy('year', 'asc')
            ->skip(4)
            ->get();
        // end query skip

        return $movies;
     }


     private function runWhereRaw() {
        // begin query raw
        $movies = DB::collection('movies')
            ->whereRaw([
                'imdb.votes' => ['$gte' => 1000 ],
                '$or' => [
                    ['imdb.rating' => ['$gt' => 7]],
                    ['directors' => ['$in' => [ 'Yasujiro Ozu', 'Sofia Coppola', 'Federico Fellini' ]]]
                ]
            ])
            ->get();
        // end query raw

        return $movies;
     }

     private function runElemMatch() {
        // begin query elemMatch
        $movies = DB::collection('movies')
            ->where('writers', 'elemMatch', ['$eq' => 'Lana Wilson', '$eq' => 'Maya Forbes'])
            ->get();
        // end query elemMatch

        return $movies;
     }


     private function runNear() {
        // begin query near
        $results = DB::collection('theaters')
            ->where('location.geo', 'near', [
                '$geometry' => [
                    'type' => 'Point',
                    'coordinates' => [
                        -86.6423, 33.6054
                    ],
                ],
                '$maxDistance' => 50,
            ])->get();
        // end query near

        print_r($results);
        return null;
     }

     private function runGeoWithin() {
        // begin query geoWithin
        $results = DB::collection('theaters')
            ->where('location.geo', 'geoWithin', [
                '$geometry' => [
                    'type' => 'Polygon',
                    'coordinates' => [
                    [
                        [-72, 40], [-74, 41], [-72, 39], [-72, 40]
                    ],
            ]]])->get();
        // end query geoWithin

        print_r($results);
        return null;
     }


     private function runGeoIntersects() {
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

        print_r($results);
        return null;
     }

     private function runGeoNear() {
        // begin query geoNear
        $results = DB::collection('theaters')
            ->whereRaw([
                function($collection) {
                    return $collection->aggregate([
                        [
                            '$geoNear' => [
                                'near' => [
                                    'type' => 'Point',
                                    'coordinates' => [ -118.34, 34.10 ],
                                ],
                                'distanceField' => 'dist.calculated',
                                'maxDistance' => 500,
                                'includeLocs' => 'dist.location',
                                'spherical' => true,
                            ]
                         ]
                    ]);
            }])->get();
        // end query geoNear

        print_r($results);
        return null;
     }

     private function runAggregate() {
        $results = DB::collection('theaters')
        ->whereRaw([
            function($collection) {
                return $collection->aggregate([
                    [ '$match' => [ 'theaterId', '>=', 8013] ]]);

        }])->get();

        print_r($results);
        return null;
     }

     private function runProjection() {
        // begin query projection
        $result = DB::collection('movies')
            ->where('imdb.rating', '>', 8.5)
            ->project([
                'title' => 1,
                'cast' => ['$slice' => [1, 3]],
            ])
            ->get();
        // end query projection
        print_r($result->toJson());
        return null;
     }

     private function runProjectionWithPagination() {
        // begin query projection with pagination
        $resultsPerPage = 15;
        $projectionFields = ['title', 'runtime', 'imdb.rating'];

        $movies = DB::collection('movies')
            ->paginate($resultsPerPage, $projectionFields)
            ->get();
        // end query projection with pagination

        return $movies;

     }

     private function runCursorTimeout() {
        // begin query cursor timeout
        $movies = DB::collection('movies')
            ->timeout(2) // value in seconds
            ->where('year', 2001)
            ->get();
        // end query cursor timeout

        return $movies;
     }

     private function runUpsert() {
        // begin upsert
        $movies = DB::collection('movies')
            ->where('title', 'Will Hunting')
            ->update([
                     'plot' => 'An autobiographical movie',
                     'year' => 1998,
                     'writers' => [ 'Will Hunting' ],
                ],
                ['upsert' => true]);
        // end upsert

        return $movies;
     }

     private function runIncrement() {
        // begin increment
        $result = DB::collection('movies')
            ->where('title', 'Field of Dreams')
            ->increment('imdb.votes', 3000);
        // end increment

        print_r($result);
        return $result;
     }

     private function runDecrement() {
        // begin decrement
        $result = DB::collection('movies')
            ->where('title', 'Sharknado')
            ->decrement('imdb.rating', 0.2);
        // end decrement

        print_r($result);
        return $result;
     }


     private function runPush() {
        // begin push
        $result = DB::collection('movies')
            ->where('title', 'Office Space')
            ->push('cast', 'Gary Cole');
        // end push

        print_r($result);
        return $result;
     }


     private function runPull() {
        // begin pull
        $result = DB::collection('movies')
            ->where('title', 'Iron Man')
            ->pull('genres', 'Adventure');
        // end pull

        print_r($result);
        return $result;
     }

     private function runUnset() {
        // begin unset
        $result = DB::collection('movies')
            ->where('title', 'Final Accord')
            ->unset('tomatoes.viewer');
        // end unset

        print_r($result);
        return $result;
     }

    /**
     * Display the specified resource.
     */
    public function show()
    {

        $result = null;
        //$result = $this->runWhere();

        //$result = $this->runOrWhere();

        //$result = $this->runAndWhere();

       // $result = $this->runNestedLogical();

        //$result = $this->runWhereNot();

        //$result = $this->runWhereBetween();

        //$result = $this->runWhereNull();

        //$result = $this->runWhereDate();

        //$result = $this->runLike();

        //$result = $this->runExists();

        //$result = $this->runAll();

        //$result = $this->runSize();

        //$result = $this->runType();

        //$result = $this->runMod();

        //$result = $this->runRegex();

        //$result = $this->runWhereRaw();

        //$result = $this->runElemMatch();

        //$result = $this->runNear();

        //$result = $this->runGeoWithin();

        //$result = $this->runGeoIntersects();

        //$result = $this->runGeoNear();
        //TODO $result = $this->runAggregate();

        //$result = $this->runWhereIn();

        //$result = $this->runDistinct();

        //$result = $this->runWhereDistinct();

        //$result = $this->runOrderBy();

        $result = $this->runGroupBy();

        //$result = $this->runAggCount();

       //$result = $this->runAggMax();

        //$result = $this->runAggMin();

        //$result = $this->runAggAvg();

        //$result = $this->runAggSum();

        //$result = $this->runAggWithFilter();

        //$result = $this->runSkip();

        //$result = Movie::where('year', 1999)->get(); //first();

        //$result = $this->runProjection();

        //$result = $this->runCursorTimeout();

        //$result = $this->runUpsert();

        //$result = $this->runIncrement();

        //$result = $this->runDecrement();

        //$result = $this->runPush();

        //$result = $this->runPull();

        //$result = $this->runUnset();

        //$result = Movie::first();
      return view('browse_movies', [
        'movies' => $result
      ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Movie $movie) {}

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Movie $movie) {}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Movie $movie) {}
}
