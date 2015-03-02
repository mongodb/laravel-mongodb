<?php

class EmbedsOneTest extends TestCase {

    public function tearDown()
    {
        User::truncate();
        Mockery::close();
    }

    public function testEmpty()
    {
        $user = User::create(['name' => 'John Doe']);
        $this->assertNull($user->father);
    }

    public function testSaveNewModel()
    {
        $user = User::create(['name' => 'John Doe']);
        $father = new User(['name' => 'Mark Doe']);

        $result = $user->father()->save($father);
        $this->assertArrayHasKey('father', $user->getRelations());
        $this->assertTrue(array_key_exists('father', $user->getAttributes()));
        $this->assertInstanceOf('User', $result);
        $this->assertInstanceOf('User', $user->father);
        $this->assertEquals($user->father->name, 'Mark Doe');

        $user = User::find($user->_id);
        $this->assertArrayNotHasKey('father', $user->getRelations());
        $this->assertTrue(array_key_exists('father', $user->getAttributes()));
        $this->assertInstanceOf('User', $user->father);
        $this->assertEquals($user->father->name, 'Mark Doe');
        $this->assertArrayHasKey('father', $user->getRelations());
    }

    public function testCreateNewModel()
    {
        $user = User::create(['name' => 'John Doe']);

        $user->father()->create(['name' => 'Mark Doe']);
        $this->assertArrayHasKey('father', $user->getRelations());
        $this->assertTrue(array_key_exists('father', $user->getAttributes()));
        $this->assertInstanceOf('User', $user->father);
        $this->assertEquals($user->father->name, 'Mark Doe');

        $user = User::find($user->_id);
        $this->assertArrayNotHasKey('father', $user->getRelations());
        $this->assertTrue(array_key_exists('father', $user->getAttributes()));
        $this->assertInstanceOf('User', $user->father);
        $this->assertEquals($user->father->name, 'Mark Doe');
        $this->assertArrayHasKey('father', $user->getRelations());
    }

    public function testEagerLoading()
    {
        User::create(['name' => 'John Doe'])->father()->create(['name' => 'Mark Doe']);
        User::create(['name' => 'Tom Doe'])->father()->create(['name' => 'Mark Doe']);
        User::create(['name' => 'Dennis Doe'])->father()->create(['name' => 'Mark Doe']);

        $users = User::get();
        $this->assertCount(3, $users);
        foreach ($users as $user)
        {
            $this->assertArrayNotHasKey('father', $user->getRelations());
        }

        $users = User::with('father')->get();
        $this->assertCount(3, $users);
        foreach ($users as $user)
        {
            $this->assertArrayHasKey('father', $user->getRelations());
        }
    }

    public function testWhereHas()
    {
        // User::create(['name' => 'John Doe'])->father()->create(['name' => 'Mark Doe']);
        // User::create(['name' => 'Tom Doe']);
        // User::create(['name' => 'Dennis Doe'])->father()->create(['name' => 'Mark Doe']);

        // $users = User::has('father')->get();

        // $this->assertEquals($users[0]->name, 'John Doe');
        // $this->assertEquals($users[1]->name, 'Dennis Doe');
    }

    public function testAssociate()
    {
        $user = User::create(['name' => 'John Doe']);
        $father = new User(['name' => 'Mark Doe']);

        $user->father()->associate($father);
        $this->assertArrayHasKey('father', $user->getRelations());
        $this->assertInstanceOf('User', $user->father);
        $this->assertEquals($user->father->name, 'Mark Doe');

        $user = User::find($user->_id);
        $this->assertArrayNotHasKey('father', $user->getRelations());
        $this->assertNull($user->father);
    }

    public function testUpdate()
    {
        $user = User::create(['name' => 'John Doe']);
        $father = $user->father()->create(['name' => 'Mark Doe']);

        $user->father()->update(['age' => 60]);

        $user = User::find($user->_id);
        $this->assertEquals($user->father->name, 'Mark Doe');
        $this->assertEquals($user->father->age, 60);
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
