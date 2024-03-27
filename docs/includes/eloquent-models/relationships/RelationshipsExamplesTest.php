<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Cargo;
use App\Models\Moon;
use App\Models\Orbit;
use App\Models\Passenger;
use App\Models\Planet;
use App\Models\SpaceExplorer;
use App\Models\SpaceShip;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\SQLiteBuilder;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Tests\TestCase;

use function assert;

class RelationshipsExamplesTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testOneToOne(): void
    {
        require_once __DIR__ . '/one-to-one/Orbit.php';
        require_once __DIR__ . '/one-to-one/Planet.php';

        // Clear the database
        Planet::truncate();
        Orbit::truncate();

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

        $planet = Planet::first();
        $this->assertInstanceOf(Planet::class, $planet);
        $this->assertInstanceOf(Orbit::class, $planet->orbit);

        // begin planet orbit dynamic property example
        $planet = Planet::first();
        $relatedOrbit = $planet->orbit;

        $orbit = Orbit::first();
        $relatedPlanet = $orbit->planet;
        // end planet orbit dynamic property example

        $this->assertInstanceOf(Orbit::class, $relatedOrbit);
        $this->assertInstanceOf(Planet::class, $relatedPlanet);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testOneToMany(): void
    {
        require_once __DIR__ . '/one-to-many/Planet.php';
        require_once __DIR__ . '/one-to-many/Moon.php';

        // Clear the database
        Planet::truncate();
        Moon::truncate();

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

        $planet = Planet::first();
        $this->assertInstanceOf(Planet::class, $planet);
        $this->assertCount(2, $planet->moons);
        $this->assertInstanceOf(Moon::class, $planet->moons->first());

        // begin planet moons dynamic property example
        $planet = Planet::first();
        $relatedMoons = $planet->moons;

        $moon = Moon::first();
        $relatedPlanet = $moon->planet;
        // end planet moons dynamic property example

        $this->assertInstanceOf(Moon::class, $relatedMoons->first());
        $this->assertInstanceOf(Planet::class, $relatedPlanet);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testManyToMany(): void
    {
        require_once __DIR__ . '/many-to-many/Planet.php';
        require_once __DIR__ . '/many-to-many/SpaceExplorer.php';

        // Clear the database
        Planet::truncate();
        SpaceExplorer::truncate();

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

        $planet = Planet::where('name', 'Earth')->first();
        $this->assertInstanceOf(Planet::class, $planet);
        $this->assertCount(3, $planet->visitors);
        $this->assertInstanceOf(SpaceExplorer::class, $planet->visitors->first());

        $explorer = SpaceExplorer::where('name', 'Jean-Luc Picard')->first();
        $this->assertInstanceOf(SpaceExplorer::class, $explorer);
        $this->assertCount(3, $explorer->planetsVisited);
        $this->assertInstanceOf(Planet::class, $explorer->planetsVisited->first());

        // begin many-to-many dynamic property example
        $planet = Planet::first();
        $explorers = $planet->visitors;

        $spaceExplorer = SpaceExplorer::first();
        $explored = $spaceExplorer->planetsVisited;
        // end many-to-many dynamic property example

        $this->assertCount(3, $explorers);
        $this->assertInstanceOf(SpaceExplorer::class, $explorers->first());
        $this->assertCount(2, $explored);
        $this->assertInstanceOf(Planet::class, $explored->first());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testEmbedsMany(): void
    {
        require_once __DIR__ . '/embeds/Cargo.php';
        require_once __DIR__ . '/embeds/SpaceShip.php';

        // Clear the database
        SpaceShip::truncate();

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

        $spaceship = SpaceShip::first();
        $this->assertInstanceOf(SpaceShip::class, $spaceship);
        $this->assertCount(2, $spaceship->cargo);
        $this->assertInstanceOf(Cargo::class, $spaceship->cargo->first());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testCrossDatabase(): void
    {
        require_once __DIR__ . '/cross-db/Passenger.php';
        require_once __DIR__ . '/cross-db/SpaceShip.php';

        $schema = Schema::connection('sqlite');
        assert($schema instanceof SQLiteBuilder);

        $schema->dropIfExists('space_ships');
        $schema->create('space_ships', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // Clear the database
        Passenger::truncate();

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

        $spaceship = SpaceShip::first();
        $this->assertInstanceOf(SpaceShip::class, $spaceship);
        $this->assertCount(2, $spaceship->passengers);
        $this->assertInstanceOf(Passenger::class, $spaceship->passengers->first());

        $passenger = Passenger::first();
        $this->assertInstanceOf(Passenger::class, $passenger);
    }
}
