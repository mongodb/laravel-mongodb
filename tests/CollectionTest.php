<?php

declare(strict_types=1);

use Jenssegers\Mongodb\Collection;
use Jenssegers\Mongodb\Connection;
use MongoDB\BSON\ObjectID;
use MongoDB\Collection as MongoCollection;
use Mockery as m;

class CollectionTest extends TestCase
{
    public function testExecuteMethodCall()
    {
        $return = ['foo' => 'bar'];
        $where = ['id' => new ObjectID('56f94800911dcc276b5723dd')];
        $time = 1.1;
        $queryString = 'name-collection.findOne({"id":"56f94800911dcc276b5723dd"})';

        $mongoCollection = $this->getMockBuilder(MongoCollection::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mongoCollection->expects($this->once())->method('findOne')->with($where)->willReturn($return);
        $mongoCollection->expects($this->once())->method('getCollectionName')->willReturn('name-collection');

        $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();
        $connection->expects($this->once())->method('getElapsedTime')->willReturn($time);
        $connection->expects($this->once())->method('logQuery')->with($queryString, [], $time);

        $collection = new Collection($connection, $mongoCollection);

        $this->assertEquals($return, $collection->findOne($where));
    }

    public function testRetryMethodCallWhenNotPrimaryExceptionCatched(){
        $return = ['foo' => 'bar'];
        $where = ['id' => new ObjectID('56f94800911dcc276b5723dd')];
        $time = 1.1;
        $queryString = 'name-collection.findOne({"id":"56f94800911dcc276b5723dd"})';

        $mongoCollection= m::mock(MongoCollection::class);

        $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();

        $collection = new Collection($connection, $mongoCollection);
        $this->expectException(Exception::class);
        $result = $collection->findOne($where);

        $connection->expects($this->once())->method('getElapsedTime')->willReturn($time);
        $connection->expects($this->once())->method('logQuery')->with($queryString, [], $time);


        $this->assertEquals($return, $result);
    }
}

