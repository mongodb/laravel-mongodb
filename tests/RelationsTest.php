<?php

class RelationsTest extends PHPUnit_Framework_TestCase {

	public function setUp() {
		User::truncate();
		Book::truncate();
		Item::truncate();
		Role::truncate();
		Client::truncate();
	}

	public function tearDown()
	{
		User::truncate();
		Book::truncate();
		Item::truncate();
		Role::truncate();
		Client::truncate();
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

		$items = Item::with('user')->get();

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
	
	public function testHasManyAndBelongsTo()
	{
		$user = User::create(array('name' => 'John Doe'));
		
		$user->clients()->save(new Client(array('name' => 'Pork Pies Ltd.')));
		$user->clients()->create(array('name' => 'Buffet Bar Inc.'));
		
		$user = User::with('clients')->find($user->_id);
		
		$client = Client::with('users')->first();
		
		$clients = $client->getRelation('users');
		$users = $user->getRelation('clients');
		
		$this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $users);
		$this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $clients);
		$this->assertInstanceOf('Client', $users[0]);
		$this->assertInstanceOf('User', $clients[0]);
		$this->assertCount(2, $user->clients);
		$this->assertCount(1, $client->users);
		
		// Now create a new user to an existing client
		$client->users()->create(array('name' => 'Jane Doe'));
		
		$otherClient = User::where('name', '=', 'Jane Doe')->first()->clients()->get();
		
		$this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $otherClient);
		$this->assertInstanceOf('Client', $otherClient[0]);
		$this->assertCount(1, $otherClient);
		
		// Now attach an existing client to an existing user
		$user = User::where('name', '=', 'Jane Doe')->first();
		$client = Client::Where('name', '=', 'Buffet Bar Inc.')->first();
		
		// Check the models are what they should be
		$this->assertInstanceOf('Client', $client);
		$this->assertInstanceOf('User', $user);
		
		// Assert they are not attached
		$this->assertFalse(in_array($client->_id, $user->client_ids));
		$this->assertFalse(in_array($user->_id, $client->user_ids));

		// Attach the client to the user
		$user->clients()->attach($client);
		
		// Get the new user model
		$user = User::where('name', '=', 'Jane Doe')->first();
		$client = Client::Where('name', '=', 'Buffet Bar Inc.')->first();

		// Assert they are attached
		$this->assertTrue(in_array($client->_id, $user->client_ids));
		$this->assertTrue(in_array($user->_id, $client->user_ids));
	}

	public function testHasManyAndBelongsToAttachesExistingModels()
	{
		$user = User::create(array('name' => 'John Doe', 'client_ids' => array('1234523')));
		
		$clients = array(
			Client::create(array('name' => 'Pork Pies Ltd.'))->_id,
			Client::create(array('name' => 'Buffet Bar Inc.'))->_id
		);
		
		$moreClients = array(
			Client::create(array('name' => 'Boloni Ltd.'))->_id,
			Client::create(array('name' => 'Meatballs Inc.'))->_id
		);
			
		$user->clients()->sync($clients);
		
		$user = User::with('clients')->find($user->_id);
		
		// Assert non attached ID's are detached succesfully
		$this->assertFalse(in_array('1234523', $user->client_ids));
		
		// Assert there are two client objects in the relationship
		$this->assertCount(2, $user->clients);
		
		$user->clients()->sync($moreClients);
		
		$user = User::with('clients')->find($user->_id);
		
		// Assert there are now 4 client objects in the relationship
		$this->assertCount(4, $user->clients);
	}
}
