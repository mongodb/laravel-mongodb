<?php

use Illuminate\Database\Eloquent\Collection;

class RelationsTest extends PHPUnit_Framework_TestCase {

    public function setUp() {
    }

    public function tearDown()
    {
        User::truncate();
        Book::truncate();
        Item::truncate();
        Role::truncate();
        Client::truncate();
        Group::truncate();
        Photo::truncate();
    }

    public function testHasMany()
    {
        $author = User::create(array('name' => 'George R. R. Martin'));
        Book::create(array('title' => 'A Game of Thrones', 'author_id' => $author->_id));
        Book::create(array('title' => 'A Clash of Kings', 'author_id' => $author->_id));

        $books = $author->books;
        $this->assertEquals(2, count($books));

        $user = User::create(array('name' => 'John Doe'));
        Item::create(array('type' => 'knife', 'user_id' => $user->_id));
        Item::create(array('type' => 'shield', 'user_id' => $user->_id));
        Item::create(array('type' => 'sword', 'user_id' => $user->_id));
        Item::create(array('type' => 'bag', 'user_id' => null));

        $items = $user->items;
        $this->assertEquals(3, count($items));
    }

    public function testBelongsTo()
    {
        $user = User::create(array('name' => 'George R. R. Martin'));
        Book::create(array('title' => 'A Game of Thrones', 'author_id' => $user->_id));
        $book = Book::create(array('title' => 'A Clash of Kings', 'author_id' => $user->_id));

        $author = $book->author;
        $this->assertEquals('George R. R. Martin', $author->name);

        $user = User::create(array('name' => 'John Doe'));
        $item = Item::create(array('type' => 'sword', 'user_id' => $user->_id));

        $owner = $item->user;
        $this->assertEquals('John Doe', $owner->name);
    }

    public function testHasOne()
    {
        $user = User::create(array('name' => 'John Doe'));
        Role::create(array('type' => 'admin', 'user_id' => $user->_id));

        $role = $user->role;
        $this->assertEquals('admin', $role->type);
    }

    public function testWithBelongsTo()
    {
        $user = User::create(array('name' => 'John Doe'));
        Item::create(array('type' => 'knife', 'user_id' => $user->_id));
        Item::create(array('type' => 'shield', 'user_id' => $user->_id));
        Item::create(array('type' => 'sword', 'user_id' => $user->_id));
        Item::create(array('type' => 'bag', 'user_id' => null));

        $items = Item::with('user')->orderBy('user_id', 'desc')->get();

        $user = $items[0]->getRelation('user');
        $this->assertInstanceOf('User', $user);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals(1, count($items[0]->getRelations()));
        $this->assertEquals(null, $items[3]->getRelation('user'));
    }

    public function testWithHashMany()
    {
        $user = User::create(array('name' => 'John Doe'));
        Item::create(array('type' => 'knife', 'user_id' => $user->_id));
        Item::create(array('type' => 'shield', 'user_id' => $user->_id));
        Item::create(array('type' => 'sword', 'user_id' => $user->_id));
        Item::create(array('type' => 'bag', 'user_id' => null));

        $user = User::with('items')->find($user->_id);

        $items = $user->getRelation('items');
        $this->assertEquals(3, count($items));
        $this->assertInstanceOf('Item', $items[0]);
    }

    public function testWithHasOne()
    {
        $user = User::create(array('name' => 'John Doe'));
        Role::create(array('type' => 'admin', 'user_id' => $user->_id));
        Role::create(array('type' => 'guest', 'user_id' => $user->_id));

        $user = User::with('role')->find($user->_id);

        $role = $user->getRelation('role');
        $this->assertInstanceOf('Role', $role);
        $this->assertEquals('admin', $role->type);
    }

    public function testEasyRelation()
    {
        // Has Many
        $user = User::create(array('name' => 'John Doe'));
        $item = Item::create(array('type' => 'knife'));
        $user->items()->save($item);

        $user = User::find($user->_id);
        $items = $user->items;
        $this->assertEquals(1, count($items));
        $this->assertInstanceOf('Item', $items[0]);

        // Has one
        $user = User::create(array('name' => 'John Doe'));
        $role = Role::create(array('type' => 'admin'));
        $user->role()->save($role);

        $user = User::find($user->_id);
        $role = $user->role;
        $this->assertInstanceOf('Role', $role);
        $this->assertEquals('admin', $role->type);
    }

    public function testBelongsToMany()
    {
        $user = User::create(array('name' => 'John Doe'));

        // Add 2 clients
        $user->clients()->save(new Client(array('name' => 'Pork Pies Ltd.')));
        $user->clients()->create(array('name' => 'Buffet Bar Inc.'));

        // Refetch
        $user = User::with('clients')->find($user->_id);
        $client = Client::with('users')->first();

        // Check for relation attributes
        $this->assertTrue(array_key_exists('user_ids', $client->getAttributes()));
        $this->assertTrue(array_key_exists('client_ids', $user->getAttributes()));

        $clients = $client->getRelation('users');
        $users = $user->getRelation('clients');

        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $users);
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $clients);
        $this->assertInstanceOf('Client', $users[0]);
        $this->assertInstanceOf('User', $clients[0]);
        $this->assertCount(2, $user->clients);
        $this->assertCount(1, $client->users);

        // Now create a new user to an existing client
        $user = $client->users()->create(array('name' => 'Jane Doe'));

        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $user->clients);
        $this->assertInstanceOf('Client', $user->clients->first());
        $this->assertCount(1, $user->clients);

        // Get user and unattached client
        $user = User::where('name', '=', 'Jane Doe')->first();
        $client = Client::Where('name', '=', 'Buffet Bar Inc.')->first();

        // Check the models are what they should be
        $this->assertInstanceOf('Client', $client);
        $this->assertInstanceOf('User', $user);

        // Assert they are not attached
        $this->assertFalse(in_array($client->_id, $user->client_ids));
        $this->assertFalse(in_array($user->_id, $client->user_ids));
        $this->assertCount(1, $user->clients);
        $this->assertCount(1, $client->users);

        // Attach the client to the user
        $user->clients()->attach($client);

        // Get the new user model
        $user = User::where('name', '=', 'Jane Doe')->first();
        $client = Client::Where('name', '=', 'Buffet Bar Inc.')->first();

        // Assert they are attached
        $this->assertTrue(in_array($client->_id, $user->client_ids));
        $this->assertTrue(in_array($user->_id, $client->user_ids));
        $this->assertCount(2, $user->clients);
        $this->assertCount(2, $client->users);

        // Detach clients from user
        $user->clients()->sync(array());

        // Get the new user model
        $user = User::where('name', '=', 'Jane Doe')->first();
        $client = Client::Where('name', '=', 'Buffet Bar Inc.')->first();

        // Assert they are not attached
        $this->assertFalse(in_array($client->_id, $user->client_ids));
        $this->assertFalse(in_array($user->_id, $client->user_ids));
        $this->assertCount(0, $user->clients);
        $this->assertCount(1, $client->users);
    }

    public function testBelongsToManyAttachesExistingModels()
    {
        $user = User::create(array('name' => 'John Doe', 'client_ids' => array('1234523')));

        $clients = array(
            Client::create(array('name' => 'Pork Pies Ltd.'))->_id,
            Client::create(array('name' => 'Buffet Bar Inc.'))->_id
        );

        $moreClients = array(
            Client::create(array('name' => 'synced Boloni Ltd.'))->_id,
            Client::create(array('name' => 'synced Meatballs Inc.'))->_id
        );

        // Sync multiple records
        $user->clients()->sync($clients);

        $user = User::with('clients')->find($user->_id);

        // Assert non attached ID's are detached succesfully
        $this->assertFalse(in_array('1234523', $user->client_ids));

        // Assert there are two client objects in the relationship
        $this->assertCount(2, $user->clients);

        // Add more clients
        $user->clients()->sync($moreClients);

        // Refetch
        $user = User::with('clients')->find($user->_id);

        // Assert there are now still 2 client objects in the relationship
        $this->assertCount(2, $user->clients);

        // Assert that the new relationships name start with synced
        $this->assertStringStartsWith('synced', $user->clients[0]->name);
        $this->assertStringStartsWith('synced', $user->clients[1]->name);
    }

    public function testBelongsToManyCustom()
    {
        $user = User::create(array('name' => 'John Doe'));
        $group = $user->groups()->create(array('name' => 'Admins'));

        // Refetch
        $user = User::find($user->_id);
        $group = Group::find($group->_id);

        // Check for custom relation attributes
        $this->assertTrue(array_key_exists('users', $group->getAttributes()));
        $this->assertTrue(array_key_exists('groups', $user->getAttributes()));

        // Assert they are attached
        $this->assertTrue(in_array($group->_id, $user->groups));
        $this->assertTrue(in_array($user->_id, $group->users));
        $this->assertEquals($group->_id, $user->groups()->first()->_id);
        $this->assertEquals($user->_id, $group->users()->first()->_id);
    }

    public function testMorph()
    {
        $user = User::create(array('name' => 'John Doe'));
        $client = Client::create(array('name' => 'Jane Doe'));

        $photo = Photo::create(array('url' => 'http://graph.facebook.com/john.doe/picture'));
        $photo = $user->photos()->save($photo);

        $this->assertEquals(1, $user->photos->count());
        $this->assertEquals($photo->id, $user->photos->first()->id);

        $photo = Photo::create(array('url' => 'http://graph.facebook.com/john.doe/picture'));
        $client->photos()->save($photo);
        $this->assertEquals(1, $client->photos->count());
        $this->assertEquals($photo->id, $client->photos->first()->id);

        $photo = Photo::first();
        $this->assertEquals($photo->imageable->name, $user->name);
    }

    public function testEmbedsManySave()
    {
        $user = User::create(array('name' => 'John Doe'));
        $address = new Address(array('city' => 'London'));

        $address = $user->addresses()->save($address);
        $this->assertEquals(array('London'), $user->addresses->lists('city'));
        $this->assertInstanceOf('DateTime', $address->created_at);
        $this->assertInstanceOf('DateTime', $address->updated_at);
        $this->assertNotNull($address->_id);

        $address = $user->addresses()->save(new Address(array('city' => 'Paris')));

        $user = User::find($user->_id);
        $this->assertEquals(array('London', 'Paris'), $user->addresses->lists('city'));

        $address->city = 'New York';
        $user->addresses()->save($address);

        $this->assertEquals(2, count($user->addresses));
        $this->assertEquals(2, count($user->addresses()->get()));
        $this->assertEquals(2, $user->addresses->count());
        $this->assertEquals(array('London', 'New York'), $user->addresses->lists('city'));

        $freshUser = User::find($user->_id);
        $this->assertEquals(array('London', 'New York'), $freshUser->addresses->lists('city'));

        $address = $user->addresses->first();
        $this->assertEquals('London', $address->city);
        $this->assertInstanceOf('DateTime', $address->created_at);
        $this->assertInstanceOf('DateTime', $address->updated_at);
        $this->assertInstanceOf('User', $address->user);

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

    public function testEmbedsManySaveMany()
    {
        $user = User::create(array('name' => 'John Doe'));
        $user->addresses()->saveMany(array(new Address(array('city' => 'London')), new Address(array('city' => 'Bristol'))));
        $this->assertEquals(array('London', 'Bristol'), $user->addresses->lists('city'));

        $freshUser = User::find($user->id);
        $this->assertEquals(array('London', 'Bristol'), $freshUser->addresses->lists('city'));
    }

    public function testEmbedsManyCreate()
    {
        $user = User::create(array('name' => 'John Doe'));
        $user->addresses()->create(array('city' => 'Bruxelles'));
        $this->assertEquals(array('Bruxelles'), $user->addresses->lists('city'));

        $freshUser = User::find($user->id);
        $this->assertEquals(array('Bruxelles'), $freshUser->addresses->lists('city'));
    }

    public function testEmbedsManyDestroy()
    {
        $user = User::create(array('name' => 'John Doe'));
        $user->addresses()->saveMany(array(new Address(array('city' => 'London')), new Address(array('city' => 'Bristol')), new Address(array('city' => 'Bruxelles'))));

        $address = $user->addresses->first();
        $user->addresses()->destroy($address->_id);
        $this->assertEquals(array('Bristol', 'Bruxelles'), $user->addresses->lists('city'));

        $address = $user->addresses->first();
        $user->addresses()->destroy($address);
        $this->assertEquals(array('Bruxelles'), $user->addresses->lists('city'));

        $user->addresses()->create(array('city' => 'Paris'));
        $user->addresses()->create(array('city' => 'San Francisco'));

        $user = User::find($user->id);
        $this->assertEquals(array('Bruxelles', 'Paris', 'San Francisco'), $user->addresses->lists('city'));

        $ids = $user->addresses->lists('_id');
        $user->addresses()->destroy($ids);
        $this->assertEquals(array(), $user->addresses->lists('city'));

        $freshUser = User::find($user->id);
        $this->assertEquals(array(), $freshUser->addresses->lists('city'));
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

}
