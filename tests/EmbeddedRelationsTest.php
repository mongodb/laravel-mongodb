<?php

class EmbeddedRelationsTest extends TestCase {

    public function tearDown()
    {
        Mockery::close();

        User::truncate();
        Book::truncate();
        Item::truncate();
        Role::truncate();
        Client::truncate();
        Group::truncate();
        Photo::truncate();
    }

    public function testEmbedsManySave()
    {
        $user = User::create(array('name' => 'John Doe'));
        $address = new Address(array('city' => 'London'));

        $address->setEventDispatcher($events = Mockery::mock('Illuminate\Events\Dispatcher'));
        $events->shouldReceive('until')->once()->with('eloquent.saving: '.get_class($address), $address)->andReturn(true);
        $events->shouldReceive('until')->once()->with('eloquent.creating: '.get_class($address), $address)->andReturn(true);
        $events->shouldReceive('fire')->once()->with('eloquent.created: '.get_class($address), $address);
        $events->shouldReceive('fire')->once()->with('eloquent.saved: '.get_class($address), $address);

        $address = $user->addresses()->save($address);
        $address->unsetEventDispatcher();

        $this->assertNotNull($user->_addresses);
        $this->assertEquals(array('London'), $user->addresses->lists('city'));
        $this->assertInstanceOf('DateTime', $address->created_at);
        $this->assertInstanceOf('DateTime', $address->updated_at);
        $this->assertNotNull($address->_id);
        $this->assertTrue(is_string($address->_id));

        $raw = $address->getAttributes();
        $this->assertInstanceOf('MongoId', $raw['_id']);

        $address = $user->addresses()->save(new Address(array('city' => 'Paris')));

        $user = User::find($user->_id);
        $this->assertEquals(array('London', 'Paris'), $user->addresses->lists('city'));

        $address->setEventDispatcher($events = Mockery::mock('Illuminate\Events\Dispatcher'));
        $events->shouldReceive('until')->once()->with('eloquent.saving: '.get_class($address), $address)->andReturn(true);
        $events->shouldReceive('until')->once()->with('eloquent.updating: '.get_class($address), $address)->andReturn(true);
        $events->shouldReceive('fire')->once()->with('eloquent.updated: '.get_class($address), $address);
        $events->shouldReceive('fire')->once()->with('eloquent.saved: '.get_class($address), $address);

        $address->city = 'New York';
        $user->addresses()->save($address);
        $address->unsetEventDispatcher();

        $this->assertEquals(2, count($user->addresses));
        $this->assertEquals(2, count($user->addresses()->get()));
        $this->assertEquals(2, $user->addresses->count());
        $this->assertEquals(2, $user->addresses()->count());
        $this->assertEquals(array('London', 'New York'), $user->addresses->lists('city'));

        $freshUser = User::find($user->_id);
        $this->assertEquals(array('London', 'New York'), $freshUser->addresses->lists('city'));

        $address = $user->addresses->first();
        $this->assertEquals('London', $address->city);
        $this->assertInstanceOf('DateTime', $address->created_at);
        $this->assertInstanceOf('DateTime', $address->updated_at);
        $this->assertInstanceOf('User', $address->user);
        $this->assertEmpty($address->relationsToArray()); // prevent infinite loop

        $user = User::find($user->_id);
        $user->addresses()->save(new Address(array('city' => 'Bruxelles')));
        $this->assertEquals(array('London', 'New York', 'Bruxelles'), $user->addresses->lists('city'));
        $address = $user->addresses[1];
        $address->city = "Manhattan";
        $user->addresses()->save($address);
        $this->assertEquals(array('London', 'Manhattan', 'Bruxelles'), $user->addresses->lists('city'));

        $freshUser = User::find($user->_id);
        $this->assertEquals(array('London', 'Manhattan', 'Bruxelles'), $freshUser->addresses->lists('city'));
    }

    public function testEmbedsToArray()
    {
        $user = User::create(array('name' => 'John Doe'));
        $user->addresses()->saveMany(array(new Address(array('city' => 'London')), new Address(array('city' => 'Bristol'))));

        $array = $user->toArray();
        $this->assertFalse(array_key_exists('_addresses', $array));
        $this->assertTrue(array_key_exists('addresses', $array));
    }

    public function testEmbedsManyAssociate()
    {
        $user = User::create(array('name' => 'John Doe'));
        $address = new Address(array('city' => 'London'));

        $address = $user->addresses()->associate($address);
        $this->assertNotNull($user->_addresses);
        $this->assertEquals(array('London'), $user->addresses->lists('city'));
        $this->assertNotNull($address->_id);

        $freshUser = User::find($user->_id);
        $this->assertEquals(array(), $freshUser->addresses->lists('city'));

        $address->city = 'Londinium';
        $user->addresses()->associate($address);
        $this->assertEquals(array('Londinium'), $user->addresses->lists('city'));

        $freshUser = User::find($user->_id);
        $this->assertEquals(array(), $freshUser->addresses->lists('city'));
    }

    public function testEmbedsManySaveMany()
    {
        $user = User::create(array('name' => 'John Doe'));
        $user->addresses()->saveMany(array(new Address(array('city' => 'London')), new Address(array('city' => 'Bristol'))));
        $this->assertEquals(array('London', 'Bristol'), $user->addresses->lists('city'));

        $freshUser = User::find($user->id);
        $this->assertEquals(array('London', 'Bristol'), $freshUser->addresses->lists('city'));
    }

    public function testEmbedsManyDuplicate()
    {
        $user = User::create(array('name' => 'John Doe'));
        $address = new Address(array('city' => 'London'));
        $user->addresses()->save($address);
        $user->addresses()->save($address);
        $this->assertEquals(1, $user->addresses->count());
        $this->assertEquals(array('London'), $user->addresses->lists('city'));

        $user = User::find($user->id);
        $this->assertEquals(1, $user->addresses->count());

        $address->city = 'Paris';
        $user->addresses()->save($address);
        $this->assertEquals(1, $user->addresses->count());
        $this->assertEquals(array('Paris'), $user->addresses->lists('city'));

        $user->addresses()->create(array('_id' => $address->_id, 'city' => 'Bruxelles'));
        $this->assertEquals(1, $user->addresses->count());
        $this->assertEquals(array('Bruxelles'), $user->addresses->lists('city'));
    }

    public function testEmbedsManyCreate()
    {
        $user = User::create(array());
        $address = $user->addresses()->create(array('city' => 'Bruxelles'));
        $this->assertInstanceOf('Address', $address);
        $this->assertTrue(is_string($address->_id));
        $this->assertEquals(array('Bruxelles'), $user->addresses->lists('city'));

        $raw = $address->getAttributes();
        $this->assertInstanceOf('MongoId', $raw['_id']);

        $freshUser = User::find($user->id);
        $this->assertEquals(array('Bruxelles'), $freshUser->addresses->lists('city'));

        $user = User::create(array());
        $address = $user->addresses()->create(array('_id' => '', 'city' => 'Bruxelles'));
        $this->assertTrue(is_string($address->_id));

        $raw = $address->getAttributes();
        $this->assertInstanceOf('MongoId', $raw['_id']);
    }

    public function testEmbedsManyCreateMany()
    {
        $user = User::create(array());
        list($bruxelles, $paris) = $user->addresses()->createMany(array(array('city' => 'Bruxelles'), array('city' => 'Paris')));
        $this->assertInstanceOf('Address', $bruxelles);
        $this->assertEquals('Bruxelles', $bruxelles->city);
        $this->assertEquals(array('Bruxelles', 'Paris'), $user->addresses->lists('city'));

        $freshUser = User::find($user->id);
        $this->assertEquals(array('Bruxelles', 'Paris'), $freshUser->addresses->lists('city'));
    }

    public function testEmbedsManyDestroy()
    {
        $user = User::create(array('name' => 'John Doe'));
        $user->addresses()->saveMany(array(new Address(array('city' => 'London')), new Address(array('city' => 'Bristol')), new Address(array('city' => 'Bruxelles'))));

        $address = $user->addresses->first();

        $address->setEventDispatcher($events = Mockery::mock('Illuminate\Events\Dispatcher'));
        $events->shouldReceive('until')->once()->with('eloquent.deleting: '.get_class($address), Mockery::mustBe($address))->andReturn(true);
        $events->shouldReceive('fire')->once()->with('eloquent.deleted: '.get_class($address), Mockery::mustBe($address));

        $user->addresses()->destroy($address->_id);
        $this->assertEquals(array('Bristol', 'Bruxelles'), $user->addresses->lists('city'));

        $address->unsetEventDispatcher();

        $address = $user->addresses->first();
        $user->addresses()->destroy($address);
        $this->assertEquals(array('Bruxelles'), $user->addresses->lists('city'));

        $user->addresses()->create(array('city' => 'Paris'));
        $user->addresses()->create(array('city' => 'San Francisco'));

        $freshUser = User::find($user->id);
        $this->assertEquals(array('Bruxelles', 'Paris', 'San Francisco'), $freshUser->addresses->lists('city'));

        $ids = $user->addresses->lists('_id');
        $user->addresses()->destroy($ids);
        $this->assertEquals(array(), $user->addresses->lists('city'));

        $freshUser = User::find($user->id);
        $this->assertEquals(array(), $freshUser->addresses->lists('city'));

        list($london, $bristol, $bruxelles) = $user->addresses()->saveMany(array(new Address(array('city' => 'London')), new Address(array('city' => 'Bristol')), new Address(array('city' => 'Bruxelles'))));
        $user->addresses()->destroy(array($london, $bruxelles));
        $this->assertEquals(array('Bristol'), $user->addresses->lists('city'));
    }

    public function testEmbedsManyDissociate()
    {
        $user = User::create(array());
        $cordoba = $user->addresses()->create(array('city' => 'Cordoba'));

        $user->addresses()->dissociate($cordoba->id);

        $freshUser = User::find($user->id);
        $this->assertEquals(0, $user->addresses->count());
        $this->assertEquals(1, $freshUser->addresses->count());
    }

    public function testEmbedsManyAliases()
    {
        $user = User::create(array('name' => 'John Doe'));
        $address = new Address(array('city' => 'London'));

        $address = $user->addresses()->attach($address);
        $this->assertEquals(array('London'), $user->addresses->lists('city'));

        $user->addresses()->detach($address);
        $this->assertEquals(array(), $user->addresses->lists('city'));
    }

    public function testEmbedsManyCreatingEventReturnsFalse()
    {
        $user = User::create(array('name' => 'John Doe'));
        $address = new Address(array('city' => 'London'));

        $address->setEventDispatcher($events = Mockery::mock('Illuminate\Events\Dispatcher'));
        $events->shouldReceive('until')->once()->with('eloquent.saving: '.get_class($address), $address)->andReturn(true);
        $events->shouldReceive('until')->once()->with('eloquent.creating: '.get_class($address), $address)->andReturn(false);

        $this->assertFalse($user->addresses()->save($address));
        $address->unsetEventDispatcher();
    }

    public function testEmbedsManySavingEventReturnsFalse()
    {
        $user = User::create(array('name' => 'John Doe'));
        $address = new Address(array('city' => 'Paris'));
        $address->exists = true;

        $address->setEventDispatcher($events = Mockery::mock('Illuminate\Events\Dispatcher'));
        $events->shouldReceive('until')->once()->with('eloquent.saving: '.get_class($address), $address)->andReturn(false);

        $this->assertFalse($user->addresses()->save($address));
        $address->unsetEventDispatcher();
    }

    public function testEmbedsManyUpdatingEventReturnsFalse()
    {
        $user = User::create(array('name' => 'John Doe'));
        $address = new Address(array('city' => 'New York'));
        $user->addresses()->save($address);

        $address->setEventDispatcher($events = Mockery::mock('Illuminate\Events\Dispatcher'));
        $events->shouldReceive('until')->once()->with('eloquent.saving: '.get_class($address), $address)->andReturn(true);
        $events->shouldReceive('until')->once()->with('eloquent.updating: '.get_class($address), $address)->andReturn(false);

        $address->city = 'Warsaw';

        $this->assertFalse($user->addresses()->save($address));
        $address->unsetEventDispatcher();
    }

    public function testEmbedsManyDeletingEventReturnsFalse()
    {
        $user = User::create(array('name' => 'John Doe'));
        $user->addresses()->save(new Address(array('city' => 'New York')));

        $address = $user->addresses->first();

        $address->setEventDispatcher($events = Mockery::mock('Illuminate\Events\Dispatcher'));
        $events->shouldReceive('until')->once()->with('eloquent.deleting: '.get_class($address), Mockery::mustBe($address))->andReturn(false);

        $this->assertEquals(0, $user->addresses()->destroy($address));
        $this->assertEquals(array('New York'), $user->addresses->lists('city'));

        $address->unsetEventDispatcher();
    }

    public function testEmbedsManyFindOrContains()
    {
        $user = User::create(array('name' => 'John Doe'));
        $address1 = $user->addresses()->save(new Address(array('city' => 'New York')));
        $address2 = $user->addresses()->save(new Address(array('city' => 'Paris')));

        $address = $user->addresses()->find($address1->_id);
        $this->assertEquals($address->city, $address1->city);

        $address = $user->addresses()->find($address2->_id);
        $this->assertEquals($address->city, $address2->city);

        $this->assertTrue($user->addresses()->contains($address2->_id));
        $this->assertFalse($user->addresses()->contains('123'));
    }

    public function testEmbedsManyEagerLoading()
    {
        $user1 = User::create(array('name' => 'John Doe'));
        $user1->addresses()->save(new Address(array('city' => 'New York')));
        $user1->addresses()->save(new Address(array('city' => 'Paris')));

        $user2 = User::create(array('name' => 'Jane Doe'));
        $user2->addresses()->save(new Address(array('city' => 'Berlin')));
        $user2->addresses()->save(new Address(array('city' => 'Paris')));

        $user = User::find($user1->id);
        $relations = $user->getRelations();
        $this->assertFalse(array_key_exists('addresses', $relations));
        $this->assertArrayNotHasKey('addresses', $user->toArray());

        $user = User::with('addresses')->get()->first();
        $relations = $user->getRelations();
        $this->assertTrue(array_key_exists('addresses', $relations));
        $this->assertEquals(2, $relations['addresses']->count());
        $this->assertArrayHasKey('addresses', $user->toArray());
    }

    public function testEmbedsManyDelete()
    {
        $user1 = User::create(array('name' => 'John Doe'));
        $user1->addresses()->save(new Address(array('city' => 'New York')));
        $user1->addresses()->save(new Address(array('city' => 'Paris')));

        $user2 = User::create(array('name' => 'Jane Doe'));
        $user2->addresses()->save(new Address(array('city' => 'Berlin')));
        $user2->addresses()->save(new Address(array('city' => 'Paris')));

        $user1->addresses()->delete();
        $this->assertEquals(0, $user1->addresses()->count());
        $this->assertEquals(0, $user1->addresses->count());
        $this->assertEquals(2, $user2->addresses()->count());
        $this->assertEquals(2, $user2->addresses->count());

        $user1 = User::find($user1->id);
        $user2 = User::find($user2->id);
        $this->assertEquals(0, $user1->addresses()->count());
        $this->assertEquals(0, $user1->addresses->count());
        $this->assertEquals(2, $user2->addresses()->count());
        $this->assertEquals(2, $user2->addresses->count());
    }

    public function testEmbedsManyCollectionMethods()
    {
        $user = User::create(array('name' => 'John Doe'));
        $user->addresses()->save(new Address(array('city' => 'New York')));
        $user->addresses()->save(new Address(array('city' => 'Paris')));
        $user->addresses()->save(new Address(array('city' => 'Brussels')));

        $this->assertEquals(array('New York', 'Paris', 'Brussels'), $user->addresses()->lists('city'));
        $this->assertEquals(array('Brussels', 'New York', 'Paris'), $user->addresses()->sortBy('city')->lists('city'));
        $this->assertEquals(array('Brussels', 'New York', 'Paris'), $user->addresses()->orderBy('city')->lists('city'));
        $this->assertEquals(array('Paris', 'New York', 'Brussels'), $user->addresses()->orderBy('city', 'desc')->lists('city'));
    }

    public function testEmbedsOne()
    {
        $user = User::create(array('name' => 'John Doe'));
        $father = new User(array('name' => 'Mark Doe'));

        $father->setEventDispatcher($events = Mockery::mock('Illuminate\Events\Dispatcher'));
        $events->shouldReceive('until')->once()->with('eloquent.saving: '.get_class($father), $father)->andReturn(true);
        $events->shouldReceive('until')->once()->with('eloquent.creating: '.get_class($father), $father)->andReturn(true);
        $events->shouldReceive('fire')->once()->with('eloquent.created: '.get_class($father), $father);
        $events->shouldReceive('fire')->once()->with('eloquent.saved: '.get_class($father), $father);

        $father = $user->father()->save($father);
        $father->unsetEventDispatcher();

        $this->assertNotNull($user->_father);
        $this->assertEquals('Mark Doe', $user->father->name);
        $this->assertInstanceOf('DateTime', $father->created_at);
        $this->assertInstanceOf('DateTime', $father->updated_at);
        $this->assertNotNull($father->_id);
        $this->assertTrue(is_string($father->_id));

        $raw = $father->getAttributes();
        $this->assertInstanceOf('MongoId', $raw['_id']);

        $father->setEventDispatcher($events = Mockery::mock('Illuminate\Events\Dispatcher'));
        $events->shouldReceive('until')->once()->with('eloquent.saving: '.get_class($father), $father)->andReturn(true);
        $events->shouldReceive('until')->once()->with('eloquent.updating: '.get_class($father), $father)->andReturn(true);
        $events->shouldReceive('fire')->once()->with('eloquent.updated: '.get_class($father), $father);
        $events->shouldReceive('fire')->once()->with('eloquent.saved: '.get_class($father), $father);

        $father->name = 'Tom Doe';
        $user->father()->save($father);
        $father->unsetEventDispatcher();

        $this->assertNotNull($user->_father);
        $this->assertEquals('Tom Doe', $user->father->name);

        $father = new User(array('name' => 'Jim Doe'));

        $father->setEventDispatcher($events = Mockery::mock('Illuminate\Events\Dispatcher'));
        $events->shouldReceive('until')->once()->with('eloquent.saving: '.get_class($father), $father)->andReturn(true);
        $events->shouldReceive('until')->once()->with('eloquent.creating: '.get_class($father), $father)->andReturn(true);
        $events->shouldReceive('fire')->once()->with('eloquent.created: '.get_class($father), $father);
        $events->shouldReceive('fire')->once()->with('eloquent.saved: '.get_class($father), $father);

        $father = $user->father()->save($father);
        $father->unsetEventDispatcher();

        $this->assertNotNull($user->_father);
        $this->assertEquals('Jim Doe', $user->father->name);
    }

    public function testEmbedsOneAssociate()
    {
        $user = User::create(array('name' => 'John Doe'));
        $father = new User(array('name' => 'Mark Doe'));

        $father->setEventDispatcher($events = Mockery::mock('Illuminate\Events\Dispatcher'));
        $events->shouldReceive('until')->times(0)->with('eloquent.saving: '.get_class($father), $father);

        $father = $user->father()->associate($father);
        $father->unsetEventDispatcher();

        $this->assertNotNull($user->_father);
        $this->assertEquals('Mark Doe', $user->father->name);
    }

    public function testEmbedsOneDelete()
    {
        $user = User::create(array('name' => 'John Doe'));
        $father = $user->father()->save(new User(array('name' => 'Mark Doe')));

        $user->father()->delete();
        $this->assertNull($user->_father);
        $this->assertNull($user->father);
    }

    public function testEmbedsManyToArray()
    {
        $user = User::create(array('name' => 'John Doe'));
        $user->addresses()->save(new Address(array('city' => 'New York')));
        $user->addresses()->save(new Address(array('city' => 'Paris')));
        $user->addresses()->save(new Address(array('city' => 'Brussels')));

        $array = $user->toArray();
        $this->assertArrayNotHasKey('_addresses', $array);

        $user->setExposed(array('_addresses'));
        $array = $user->toArray();
        $this->assertArrayHasKey('_addresses', $array);
    }

}
