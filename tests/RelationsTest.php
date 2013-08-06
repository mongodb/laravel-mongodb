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

}