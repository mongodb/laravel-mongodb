<?php

use Illuminate\Support\Facades\DB;

class SeederTest extends PHPUnit_Framework_TestCase {

	public function setUp() {}

	public function tearDown()
	{
		User::truncate();
	}

	public function testSeed()
	{
		$seeder = new UserTableSeeder;
		$seeder->run();

		$user = User::where('name', 'John Doe')->first();
		$this->assertTrue($user->seed);
	}

}
