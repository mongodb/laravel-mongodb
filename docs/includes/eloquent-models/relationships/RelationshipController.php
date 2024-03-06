<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Orbit;
use App\Models\Planet;
use Illuminate\Http\Request;

use function view;

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

    private function manyToMany()
    {
        // begin many-to-many save
        $planet_earth = new Planet();
        $planet_earth->name = 'Earth';
        $planet_earth->save();

        $planet_mars = new Planet();
        $planet_mars->name = 'Mars';
        $planet_mars->save();

        $planet_jupiter = new Planet();
        $planet_jupiter->name = 'Jupiter';
        $planet_jupiter->save();

        $explorer_tanya = new SpaceExplorer();
        $explorer_tanya->name = 'Tanya Kirbuk';
        $explorer_tanya->save();

        $explorer_mark = new SpaceExplorer();
        $explorer_mark->name = 'Mark Watney';
        $explorer_mark->save();

        $explorer_jeanluc = new SpaceExplorer();
        $explorer_jeanluc->name = 'Jean-Luc Picard';
        $explorer_jeanluc->save();

        $explorer_tanya->planetsVisited()->attach($planet_earth);
        $explorer_tanya->planetsVisited()->attach($planet_jupiter);
        $explorer_mark->planetsVisited()->attach($planet_earth);
        $explorer_mark->planetsVisited()->attach($planet_mars);
        $explorer_jeanluc->planetsVisited()->attach($planet_earth);
        $explorer_jeanluc->planetsVisited()->attach($planet_mars);
        $explorer_jeanluc->planetsVisited()->attach($planet_jupiter);
        // end many-to-many save
    }

    private function manyToManyDynamic()
    {
        // begin many-to-many dynamic property example
        $planet = Planet::first();
        $explorers = $planet->visitors;

        $space_explorer = SpaceExplorer:first();
        $planets_visited = $space_explorer->planetsVisited;
        // end many-to-many dynamic property example
    }

    private function embedsMany()
    {
        // begin embedsMany save
        $spaceship = new SpaceShip();
        $spaceship->name = 'The Millenium Falcon';
        $spaceship->save();

        $cargo_spice = new Cargo();
        $cargo_spice->name = 'spice';
        $cargo_spice->weight = 50;

        $cargo_hyperdrive = new Cargo();
        $cargo_hyperdrive->name = 'hyperdrive';
        $cargo_hyperdrive->weight = 25;

        $spaceship->cargo()->attach($cargo_spice);
        $spaceship->cargo()->attach($cargo_hyperdrive);
        // end embedsMany save
    }

    private function crossDatabase()
    {
        // begin cross-database save
        $spaceship = new SpaceShip();
        $spaceship->id = 1234;
        $spaceship->name = 'Nostromo';
        $spaceship->save();

        $passenger_ellen = new Passenger();
        $passenger_ellen->name = 'Ellen Ripley';

        $passenger_dwayne = new Passenger();
        $passenger_dwayne->name = 'Dwayne Hicks';

        $spaceship->passengers()->save($passenger_ellen);
        $spaceship->passengers()->save($passenger_dwayne);
        // end cross-database save
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
             ->get(),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Planet $planet)
    {
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Planet $planet)
    {
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Planet $planet)
    {
    }
}
