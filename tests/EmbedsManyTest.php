<?php

class EmbedsManyTest extends TestCase {

    public function tearDown()
    {
        User::truncate();
        Mockery::close();
    }

    public function testEmpty()
    {
        $user = User::create(['name' => 'John Doe']);
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $user->addresses);
        $this->assertCount(0, $user->addresses);
    }

    public function testSaveNewModel()
    {
        $user = User::create(['name' => 'John Doe']);
        $address = new Address(['city' => 'London']);

        $result = $user->addresses()->save($address);
        $this->assertArrayHasKey('addresses', $user->getRelations());
        $this->assertTrue(array_key_exists('addresses', $user->getAttributes()));
        $this->assertInstanceOf('Address', $result);
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $user->addresses);
        $this->assertInstanceOf('Address', $user->addresses->first());
        $this->assertEquals($user->addresses->first()->city, 'London');

        $user = User::find($user->_id);
        $this->assertArrayNotHasKey('addresses', $user->getRelations());
        $this->assertTrue(array_key_exists('addresses', $user->getAttributes()));
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $user->addresses);
        $this->assertInstanceOf('Address', $user->addresses->first());
        $this->assertEquals($user->addresses->first()->city, 'London');
        $this->assertArrayHasKey('addresses', $user->getRelations());
    }

    public function testCreateNewModel()
    {
        $user = User::create(['name' => 'John Doe']);

        $user->addresses()->create(['city' => 'London']);
        $this->assertArrayHasKey('addresses', $user->getRelations());
        $this->assertTrue(array_key_exists('addresses', $user->getAttributes()));
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $user->addresses);
        $this->assertCount(1, $user->addresses);
        $this->assertInstanceOf('Address', $user->addresses->first());
        $this->assertEquals($user->addresses->first()->city, 'London');

        $user = User::find($user->_id);
        $this->assertArrayNotHasKey('addresses', $user->getRelations());
        $this->assertTrue(array_key_exists('addresses', $user->getAttributes()));
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $user->addresses);
        $this->assertCount(1, $user->addresses);
        $this->assertInstanceOf('Address', $user->addresses->first());
        $this->assertEquals($user->addresses->first()->city, 'London');
        $this->assertArrayHasKey('addresses', $user->getRelations());
    }

    public function testSaveMultipleModels()
    {
        $user = User::create(['name' => 'John Doe']);
        $address1 = $user->addresses()->save(new Address(['city' => 'London']));
        $address2 = $user->addresses()->save(new Address(['city' => 'Paris']));

        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $user->addresses);
        $this->assertCount(2, $user->addresses);
        $this->assertEquals($user->addresses[0]->city, 'London');
        $this->assertEquals($user->addresses[1]->city, 'Paris');

        $user = User::find($user->_id);
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $user->addresses);
        $this->assertCount(2, $user->addresses);
        $this->assertEquals($user->addresses[0]->city, 'London');
        $this->assertEquals($user->addresses[1]->city, 'Paris');
    }

    public function testEagerLoading()
    {
        User::create(['name' => 'John Doe'])->addresses()->create(['city' => 'London']);
        User::create(['name' => 'Tom Doe'])->addresses()->create(['city' => 'Paris']);
        User::create(['name' => 'Dennis Doe'])->addresses()->create(['city' => 'New York']);

        $users = User::get();
        $this->assertCount(3, $users);
        foreach ($users as $user)
        {
            $this->assertArrayNotHasKey('addresses', $user->getRelations());
        }

        $users = User::with('addresses')->get();
        $this->assertCount(3, $users);
        foreach ($users as $user)
        {
            $this->assertArrayHasKey('addresses', $user->getRelations());
        }
    }

    public function testWhereHas()
    {
        // User::create(['name' => 'John Doe'])->addresses()->create(['city' => 'London']);
        // User::create(['name' => 'Tom Doe'])->addresses()->create(['city' => 'Paris']);
        // User::create(['name' => 'Dennis Doe'])->addresses()->create(['city' => 'New York']);

        // $users = User::has('addresses')->get();

        // $this->assertEquals($users[0]->name, 'John Doe');
        // $this->assertEquals($users[1]->name, 'Dennis Doe');
    }

    public function testAssociate()
    {
        $user = User::create(['name' => 'John Doe']);
        $address = new Address(['city' => 'Paris']);

        $user->addresses()->associate($address);
        $this->assertArrayHasKey('addresses', $user->getRelations());
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $user->addresses);
        $this->assertCount(1, $user->addresses);

        $user = User::find($user->_id);
        $this->assertArrayNotHasKey('addresses', $user->getRelations());
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $user->addresses);
        $this->assertCount(0, $user->addresses);
    }

    public function testUpdate()
    {
        $user = User::create(['name' => 'John Doe']);
        $address1 = $user->addresses()->create(['city' => 'London', 'visited' => false]);
        $address2 = $user->addresses()->create(['city' => 'Paris', 'visited' => false]);

        $user->addresses()->update(['visited' => true]);

        $user = User::find($user->_id);
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $user->addresses);
        $this->assertCount(2, $user->addresses);
        $this->assertEquals($user->addresses[0]->visited, true);
    }

    public function testSaveOnEmbeddedModel()
    {
        // $user = User::create(['name' => 'John Doe']);
        // $father = $user->father()->save(new User(['name' => 'Mark Doe']));

        // $father->name = 'Steve Doe';
        // $father->save();

        // $user = User::find($user->_id);
        // $this->assertEquals($user->father->name, 'Steve Doe');
    }

    public function testNestedModels()
    {
        // $user = User::create(['name' => 'John Doe']);
        // $father = $user->father()->save(new User(['name' => 'Mark Doe']));
        // $grandFather = $father->father()->save(new User(['name' => 'Tim Doe']));

        // $this->assertEquals($user->father->name, 'Steve Doe');
        // $this->assertEquals($user->father->father->name, 'Tim Doe');

        // $user = User::find($user->_id);
        // $this->assertEquals($user->father->name, 'Steve Doe');
        // $this->assertEquals($user->father->father->name, 'Tim Doe');
    }

}
