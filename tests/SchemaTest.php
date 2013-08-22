<?php
require_once('tests/app.php');

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SchemaTest extends PHPUnit_Framework_TestCase {

	public function setUp() {}

	public function tearDown()
	{
		Schema::drop('newcollection');
	}

	public function testCreate()
	{
		Schema::create('newcollection');
		$this->assertTrue(Schema::hasCollection('newcollection'));
	}

	public function testDrop()
	{
		Schema::create('newcollection');
		Schema::drop('newcollection');
		$this->assertFalse(Schema::hasCollection('newcollection'));
	}

	public function testIndex()
	{
		Schema::collection('newcollection', function($collection)
		{
			$collection->index('mykey');
		});

		$index = $this->getIndex('newcollection', 'mykey');
		$this->assertEquals(1, $index);
	}

	public function testUnique()
	{
		Schema::collection('newcollection', function($collection)
		{
			$collection->unique('uniquekey');
		});

		$index = $this->getIndex('newcollection', 'uniquekey');
		$this->assertEquals('unique', $index);
	}

	protected function getIndex($collection, $name)
	{
		$collection = DB::getCollection($collection);

		foreach ($collection->getIndexInfo() as $index)
		{
			if (isset($index['key'][$name]))
			{
				if (isset($index['unique'])) return 'unique';
				return $index['key'][$name];
			}
		}

		return false;
	}

}