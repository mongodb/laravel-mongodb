<?php

namespace App\Http\Controllers;

use App\Models\Planet;
use App\Models\Orbit;
use Illuminate\Http\Request;

class PlanetController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        // begin one-to-one save
        $planet = new Planet();
        $planet->name = 'Earth';
        $planet->diameter_km = 12742;
        $planet->save();

        $orbit = new Orbit();
        $orbit->period = 365.26;
        $orbit->direction = 'counterclockwise';

        $planet->orbit()->save($orbit);
        // end one-to-one save



        return;
    }

    /**
     * Display the specified resource.
     */

    public function show()
    {

      // begin planet-to-orbit
      $planet = Planet::first();
      $related_orbit = $planet->orbit;
      // end planet-to-orbit 

      // begin orbit-to-planet
      $orbit = Orbit::first();
      $related_planet = $orbit->planet;
      // end orbit-to-planet

      return view('browse_planets', [
        'planets' => Planet::take(10)
           ->get()
      ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Planet $planet)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Planet $planet)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Planet $planet)
    {
        //
    }
}
