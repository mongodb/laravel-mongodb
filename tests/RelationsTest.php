<?php
require_once('tests/app.php');

class RelationsTest extends PHPUnit_Framework_TestCase {

	public function setUp() {
	}

	public function tearDown()
	{
		User::truncate();
		Book::truncate();
		Item::truncate();
		Role::truncate();
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

}