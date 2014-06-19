<?php

class RelationsTest extends TestCase {

    public function tearDown()
    {
        Mockery::close();

        User::truncate();
        Client::truncate();
        Address::truncate();
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
        $this->assertEquals($user->_id, $role->user_id);

        $user = User::create(array('name' => 'Jane Doe'));
        $role = new Role(array('type' => 'user'));
        $user->role()->save($role);

        $role = $user->role;
        $this->assertEquals('user', $role->type);
        $this->assertEquals($user->_id, $role->user_id);

        $user = User::where('name', 'Jane Doe')->first();
        $role = $user->role;
        $this->assertEquals('user', $role->type);
        $this->assertEquals($user->_id, $role->user_id);
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

        $clients = $user->getRelation('clients');
        $users = $client->getRelation('users');

        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $users);
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $clients);
        $this->assertInstanceOf('Client', $clients[0]);
        $this->assertInstanceOf('User', $users[0]);
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

        $user = User::find($user->_id);
        $this->assertEquals(1, $user->photos->count());
        $this->assertEquals($photo->id, $user->photos->first()->id);

        $photo = Photo::create(array('url' => 'http://graph.facebook.com/jane.doe/picture'));
        $client->photo()->save($photo);

        $this->assertNotNull($client->photo);
        $this->assertEquals($photo->id, $client->photo->id);

        $client = Client::find($client->_id);
        $this->assertNotNull($client->photo);
        $this->assertEquals($photo->id, $client->photo->id);

        $photo = Photo::first();
        $this->assertEquals($photo->imageable->name, $user->name);

        $user = User::with('photos')->find($user->_id);
        $relations = $user->getRelations();
        $this->assertTrue(array_key_exists('photos', $relations));
        $this->assertEquals(1, $relations['photos']->count());

        $photos = Photo::with('imageable')->get();
        $relations = $photos[0]->getRelations();
        $this->assertTrue(array_key_exists('imageable', $relations));
        $this->assertInstanceOf('User', $relations['imageable']);

        $relations = $photos[1]->getRelations();
        $this->assertTrue(array_key_exists('imageable', $relations));
        $this->assertInstanceOf('Client', $relations['imageable']);
    }

    public function testHasManyHas()
    {
        $author1 = User::create(array('name' => 'George R. R. Martin'));
        $author1->books()->create(array('title' => 'A Game of Thrones', 'rating' => 5));
        $author1->books()->create(array('title' => 'A Clash of Kings', 'rating' => 5));
        $author2 = User::create(array('name' => 'John Doe'));
        $author2->books()->create(array('title' => 'My book', 'rating' => 2));
        User::create(array('name' => 'Anonymous author'));
        Book::create(array('title' => 'Anonymous book', 'rating' => 1));

        $authors = User::has('books')->get();
        $this->assertCount(2, $authors);
        $this->assertEquals('George R. R. Martin', $authors[0]->name);
        $this->assertEquals('John Doe', $authors[1]->name);

        $authors = User::has('books', '>', 1)->get();
        $this->assertCount(1, $authors);

        $authors = User::has('books', '<', 5)->get();
        $this->assertCount(3, $authors);

        $authors = User::has('books', '>=', 2)->get();
        $this->assertCount(1, $authors);

        $authors = User::has('books', '<=', 1)->get();
        $this->assertCount(2, $authors);

        $authors = User::has('books', '=', 2)->get();
        $this->assertCount(1, $authors);

        $authors = User::has('books', '!=', 2)->get();
        $this->assertCount(2, $authors);

        $authors = User::has('books', '=', 0)->get();
        $this->assertCount(1, $authors);

        $authors = User::has('books', '!=', 0)->get();
        $this->assertCount(2, $authors);

        $authors = User::whereHas('books', function($query)
        {
            $query->where('rating', 5);

        })->get();
        $this->assertCount(1, $authors);

        $authors = User::whereHas('books', function($query)
        {
            $query->where('rating', '<', 5);

        })->get();
        $this->assertCount(1, $authors);
    }

    public function testHasOneHas()
    {
        $user1 = User::create(array('name' => 'John Doe'));
        $user1->role()->create(array('title' => 'admin'));
        $user2 = User::create(array('name' => 'Jane Doe'));
        $user2->role()->create(array('title' => 'reseller'));
        User::create(array('name' => 'Mark Moe'));
        Role::create(array('title' => 'Customer'));

        $users = User::has('role')->get();
        $this->assertCount(2, $users);
        $this->assertEquals('John Doe', $users[0]->name);
        $this->assertEquals('Jane Doe', $users[1]->name);

        $users = User::has('role', '=', 0)->get();
        $this->assertCount(1, $users);

        $users = User::has('role', '!=', 0)->get();
        $this->assertCount(2, $users);
    }

    public function testNestedKeys()
    {
        $client = Client::create(array(
            'data' => array(
                'client_id' => 35298,
                'name' => 'John Doe'
            )
        ));

        $address = $client->addresses()->create(array(
            'data' => array(
                'address_id' => 1432,
                'city' => 'Paris'
            )
        ));

        $client = Client::where('data.client_id', 35298)->first();
        $this->assertEquals(1, $client->addresses->count());

        $address = $client->addresses->first();
        $this->assertEquals('Paris', $address->data['city']);
    }

}
