<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Orbit;
use App\Models\Planet;
use Illuminate\Http\Request;

class RelationshipController extends Controller
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

        $moon1 = new Moon();
        $moon1->name = 'Ganymede';
        $moon1->orbital_period = 7.15;

        $moon2 = new Moon();
        $moon2->name = 'Europa';
        $moon2->orbital_period = 3.55;

        $planet->moons()->save($moon1);
        $planet->moons()->save($moon2);
        // end one-to-many save
    }

    private function planetOrbitDynamic()
    {
        // begin planet orbit dynamic property example
        $planet = Planet::first();
        $relatedOrbit = $planet->orbit;

        $orbit = Orbit::first();
        $relatedPlanet = $orbit->planet;
        // end planet orbit dynamic property example
    }

    private function planetMoonsDynamic()
    {
        // begin planet moons dynamic property example
        $planet = Planet::first();
        $relatedMoons = $planet->moons;

        $moon = Moon::first();
        $relatedPlanet = $moon->planet;
        // end planet moons dynamic property example
    }

    private function manyToMany()
    {
        // begin many-to-many save
        $planetEarth = new Planet();
        $planetEarth->name = 'Earth';
        $planetEarth->save();

        $planetMars = new Planet();
        $planetMars->name = 'Mars';
        $planetMars->save();

        $planetJupiter = new Planet();
        $planetJupiter->name = 'Jupiter';
        $planetJupiter->save();

        $explorerTanya = new SpaceExplorer();
        $explorerTanya->name = 'Tanya Kirbuk';
        $explorerTanya->save();

        $explorerMark = new SpaceExplorer();
        $explorerMark->name = 'Mark Watney';
        $explorerMark->save();

        $explorerJeanluc = new SpaceExplorer();
        $explorerJeanluc->name = 'Jean-Luc Picard';
        $explorerJeanluc->save();

        $explorerTanya->planetsVisited()->attach($planetEarth);
        $explorerTanya->planetsVisited()->attach($planetJupiter);
        $explorerMark->planetsVisited()->attach($planetEarth);
        $explorerMark->planetsVisited()->attach($planetMars);
        $explorerJeanluc->planetsVisited()->attach($planetEarth);
        $explorerJeanluc->planetsVisited()->attach($planetMars);
        $explorerJeanluc->planetsVisited()->attach($planetJupiter);
        // end many-to-many save
    }

    private function manyToManyDynamic()
    {
        // begin many-to-many dynamic property example
        $planet = Planet::first();
        $explorers = $planet->visitors;

        $spaceExplorer = SpaceExplorer:first();
        $explored = $spaceExplorer->planetsVisited;
        // end many-to-many dynamic property example
    }

    private function embedsMany()
    {
        // begin embedsMany save
        $spaceship = new SpaceShip();
        $spaceship->name = 'The Millenium Falcon';
        $spaceship->save();

        $cargoSpice = new Cargo();
        $cargoSpice->name = 'spice';
        $cargoSpice->weight = 50;

        $cargoHyperdrive = new Cargo();
        $cargoHyperdrive->name = 'hyperdrive';
        $cargoHyperdrive->weight = 25;

        $spaceship->cargo()->attach($cargoSpice);
        $spaceship->cargo()->attach($cargoHyperdrive);
        // end embedsMany save
    }

    private function crossDatabase()
    {
        // begin cross-database save
        $spaceship = new SpaceShip();
        $spaceship->id = 1234;
        $spaceship->name = 'Nostromo';
        $spaceship->save();

        $passengerEllen = new Passenger();
        $passengerEllen->name = 'Ellen Ripley';

        $passengerDwayne = new Passenger();
        $passengerDwayne->name = 'Dwayne Hicks';

        $spaceship->passengers()->save($passengerEllen);
        $spaceship->passengers()->save($passengerDwayne);
        // end cross-database save
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
    }

    /**
     * Display the specified resource.
     */
    public function show()
    {
        return 'ok';
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
