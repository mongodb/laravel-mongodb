<?php

class SchemaTest extends TestCase {

	public function tearDown()
	{
		Schema::drop('newcollection');
	}

	public function testCreate()
	{
		Schema::create('newcollection');
		$this->assertTrue(Schema::hasCollection('newcollection'));
		$this->assertTrue(Schema::hasTable('newcollection'));
	}

	public function testCreateWithCallback()
	{
		$instance = $this;

		Schema::create('newcollection', function($collection) use ($instance)
		{
			$instance->assertInstanceOf('Jenssegers\Mongodb\Schema\Blueprint', $collection);
		});

		$this->assertTrue(Schema::hasCollection('newcollection'));
	}

	public function testDrop()
	{
		Schema::create('newcollection');
		Schema::drop('newcollection');
		$this->assertFalse(Schema::hasCollection('newcollection'));
	}

	public function testBluePrint()
	{
		$instance = $this;

		Schema::collection('newcollection', function($collection) use ($instance)
		{
			$instance->assertInstanceOf('Jenssegers\Mongodb\Schema\Blueprint', $collection);
		});

		Schema::table('newcollection', function($collection) use ($instance)
		{
			$instance->assertInstanceOf('Jenssegers\Mongodb\Schema\Blueprint', $collection);
		});
	}

	public function testIndex()
	{
		Schema::collection('newcollection', function($collection)
		{
			$collection->index('mykey');
		});

		$index = $this->getIndex('newcollection', 'mykey');
		$this->assertEquals(1, $index['key']['mykey']);

		Schema::collection('newcollection', function($collection)
		{
			$collection->index(array('mykey'));
		});

		$index = $this->getIndex('newcollection', 'mykey');
		$this->assertEquals(1, $index['key']['mykey']);
	}

	public function testUnique()
	{
		Schema::collection('newcollection', function($collection)
		{
			$collection->unique('uniquekey');
		});

		$index = $this->getIndex('newcollection', 'uniquekey');
		$this->assertEquals(1, $index['unique']);
	}

	public function testDropIndex()
	{
		Schema::collection('newcollection', function($collection)
		{
			$collection->unique('uniquekey');
			$collection->dropIndex('uniquekey');
		});

		$index = $this->getIndex('newcollection', 'uniquekey');
		$this->assertEquals(null, $index);

		Schema::collection('newcollection', function($collection)
		{
			$collection->unique('uniquekey');
			$collection->dropIndex(array('uniquekey'));
		});

		$index = $this->getIndex('newcollection', 'uniquekey');
		$this->assertEquals(null, $index);
	}

	public function testBackground()
	{
		Schema::collection('newcollection', function($collection)
		{
			$collection->background('backgroundkey');
		});

		$index = $this->getIndex('newcollection', 'backgroundkey');
		$this->assertEquals(1, $index['background']);
	}

	public function testSparse()
	{
		Schema::collection('newcollection', function($collection)
		{
			$collection->sparse('sparsekey');
		});

		$index = $this->getIndex('newcollection', 'sparsekey');
		$this->assertEquals(1, $index['sparse']);
	}

	public function testExpire()
	{
		Schema::collection('newcollection', function($collection)
		{
			$collection->expire('expirekey', 60);
		});

		$index = $this->getIndex('newcollection', 'expirekey');
		$this->assertEquals(60, $index['expireAfterSeconds']);
	}

	public function testSoftDeletes()
	{
		Schema::collection('newcollection', function($collection)
		{
			$collection->softDeletes();
		});

		Schema::collection('newcollection', function($collection)
		{
			$collection->string('email')->nullable()->index();
		});

		$index = $this->getIndex('newcollection', 'email');
		$this->assertEquals(1, $index['key']['email']);
	}

	public function testFluent()
	{
		Schema::collection('newcollection', function($collection)
		{
			$collection->string('email')->index();
			$collection->string('token')->index();
			$collection->timestamp('created_at');
		});

		$index = $this->getIndex('newcollection', 'email');
		$this->assertEquals(1, $index['key']['email']);

		$index = $this->getIndex('newcollection', 'token');
		$this->assertEquals(1, $index['key']['token']);
	}

	public function testDummies()
	{
		Schema::collection('newcollection', function($collection)
		{
			$collection->boolean('activated')->default(0);
			$collection->integer('user_id')->unsigned();
		});
	}

	protected function getIndex($collection, $name)
	{
		$collection = DB::getCollection($collection);

		foreach ($collection->getIndexInfo() as $index)
		{
			if (isset($index['key'][$name])) return $index;
		}

		return false;
	}

}
