<?php

namespace App\Http\Controllers;

use App\Models\Planet;
use App\Models\Orbit;
use Illuminate\Http\Request;

class PlanetController extends Controller
{

    private function oneToOne()
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
    }

    private function oneToMany()
    {
        // begin one-to-many save
        $planet = new Planet();
        $planet->name = 'Jupiter';
        $planet->diameter_km = 142984;
        $planet->save();

        $moon_1 = new Moon();
        $moon_1->name = 'Ganymede';
        $moon_1->orbital_period = 7.15;

        $moon_2 = new Moon();
        $moon_2->name = 'Europa';
        $moon_2->orbital_period = 3.55;

        $planet->moons()->save($moon_1);
        $planet->moons()->save($moon_2);
        // end one-to-many save
    }

    private function planetOrbitDynamic()
    {
        // begin planet orbit dynamic property example
        $planet = Planet::first();
        $related_orbit = $planet->orbit;

        $orbit = Orbit::first();
        $related_planet = $orbit->planet;
        // end planet orbit dynamic property example
    }

    private function planetMoonsDynamic()
    {
        // begin planet moons dynamic property example
        $planet = Planet::first();
        $related_moons = $planet->moons;

        $moon = Moon::first();
        $related_planet = $moon->planet;
        // end planet moons dynamic property example
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        oneToMany();

        return;
    }



    /**
     * Display the specified resource.
     */
    public function show()
    {
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
