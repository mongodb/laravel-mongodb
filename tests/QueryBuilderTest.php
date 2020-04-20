<?php
declare(strict_types=1);

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Jenssegers\Mongodb\Collection;
use Jenssegers\Mongodb\Query\Builder;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Driver\Cursor;

class QueryBuilderTest extends TestCase
{
    public function tearDown(): void
    {
        DB::collection('users')->truncate();
        DB::collection('items')->truncate();
    }

    public function testDeleteWithId()
    {
        $user = DB::collection('users')->insertGetId([
            ['name' => 'Jane Doe', 'age' => 20],
        ]);

        $user_id = (string) $user;

        DB::collection('items')->insert([
            ['name' => 'one thing', 'user_id' => $user_id],
            ['name' => 'last thing', 'user_id' => $user_id],
            ['name' => 'another thing', 'user_id' => $user_id],
            ['name' => 'one more thing', 'user_id' => $user_id],
        ]);

        $product = DB::collection('items')->first();

        $pid = (string) ($product['_id']);

        DB::collection('items')->where('user_id', $user_id)->delete($pid);

        $this->assertEquals(3, DB::collection('items')->count());

        $product = DB::collection('items')->first();

        $pid = $product['_id'];

        DB::collection('items')->where('user_id', $user_id)->delete($pid);

        DB::collection('items')->where('user_id', $user_id)->delete(md5('random-id'));

        $this->assertEquals(2, DB::collection('items')->count());
    }

    public function testCollection()
    {
        $this->assertInstanceOf(Builder::class, DB::collection('users'));
    }

    public function testGet()
    {
        $users = DB::collection('users')->get();
        $this->assertCount(0, $users);

        DB::collection('users')->insert(['name' => 'John Doe']);

        $users = DB::collection('users')->get();
        $this->assertCount(1, $users);
    }

    public function testNoDocument()
    {
        $items = DB::collection('items')->where('name', 'nothing')->get()->toArray();
        $this->assertEquals([], $items);

        $item = DB::collection('items')->where('name', 'nothing')->first();
        $this->assertNull($item);

        $item = DB::collection('items')->where('_id', '51c33d8981fec6813e00000a')->first();
        $this->assertNull($item);
    }

    public function testInsert()
    {
        DB::collection('users')->insert([
            'tags' => ['tag1', 'tag2'],
            'name' => 'John Doe',
        ]);

        $users = DB::collection('users')->get();
        $this->assertCount(1, $users);

        $user = $users[0];
        $this->assertEquals('John Doe', $user['name']);
        $this->assertIsArray($user['tags']);
    }

    public function testInsertGetId()
    {
        $id = DB::collection('users')->insertGetId(['name' => 'John Doe']);
        $this->assertInstanceOf(ObjectId::class, $id);
    }

    public function testBatchInsert()
    {
        DB::collection('users')->insert([
            [
                'tags' => ['tag1', 'tag2'],
                'name' => 'Jane Doe',
            ],
            [
                'tags' => ['tag3'],
                'name' => 'John Doe',
            ],
        ]);

        $users = DB::collection('users')->get();
        $this->assertCount(2, $users);
        $this->assertIsArray($users[0]['tags']);
    }

    public function testFind()
    {
        $id = DB::collection('users')->insertGetId(['name' => 'John Doe']);

        $user = DB::collection('users')->find($id);
        $this->assertEquals('John Doe', $user['name']);
    }

    public function testFindNull()
    {
        $user = DB::collection('users')->find(null);
        $this->assertNull($user);
    }

    public function testCount()
    {
        DB::collection('users')->insert([
            ['name' => 'Jane Doe'],
            ['name' => 'John Doe'],
        ]);

        $this->assertEquals(2, DB::collection('users')->count());
    }

    public function testUpdate()
    {
        DB::collection('users')->insert([
            ['name' => 'Jane Doe', 'age' => 20],
            ['name' => 'John Doe', 'age' => 21],
        ]);

        DB::collection('users')->where('name', 'John Doe')->update(['age' => 100]);

        $john = DB::collection('users')->where('name', 'John Doe')->first();
        $jane = DB::collection('users')->where('name', 'Jane Doe')->first();
        $this->assertEquals(100, $john['age']);
        $this->assertEquals(20, $jane['age']);
    }

    public function testDelete()
    {
        DB::collection('users')->insert([
            ['name' => 'Jane Doe', 'age' => 20],
            ['name' => 'John Doe', 'age' => 25],
        ]);

        DB::collection('users')->where('age', '<', 10)->delete();
        $this->assertEquals(2, DB::collection('users')->count());

        DB::collection('users')->where('age', '<', 25)->delete();
        $this->assertEquals(1, DB::collection('users')->count());
    }

    public function testTruncate()
    {
        DB::collection('users')->insert(['name' => 'John Doe']);
        $result = DB::collection('users')->truncate();
        $this->assertEquals(1, $result);
        $this->assertEquals(0, DB::collection('users')->count());
    }

    public function testSubKey()
    {
        DB::collection('users')->insert([
            [
                'name' => 'John Doe',
                'address' => ['country' => 'Belgium', 'city' => 'Ghent'],
            ],
            [
                'name' => 'Jane Doe',
                'address' => ['country' => 'France', 'city' => 'Paris'],
            ],
        ]);

        $users = DB::collection('users')->where('address.country', 'Belgium')->get();
        $this->assertCount(1, $users);
        $this->assertEquals('John Doe', $users[0]['name']);
    }

    public function testInArray()
    {
        DB::collection('items')->insert([
            [
                'tags' => ['tag1', 'tag2', 'tag3', 'tag4'],
            ],
            [
                'tags' => ['tag2'],
            ],
        ]);

        $items = DB::collection('items')->where('tags', 'tag2')->get();
        $this->assertCount(2, $items);

        $items = DB::collection('items')->where('tags', 'tag1')->get();
        $this->assertCount(1, $items);
    }

    public function testRaw()
    {
        DB::collection('users')->insert([
            ['name' => 'Jane Doe', 'age' => 20],
            ['name' => 'John Doe', 'age' => 25],
        ]);

        $cursor = DB::collection('users')->raw(function ($collection) {
            return $collection->find(['age' => 20]);
        });

        $this->assertInstanceOf(Cursor::class, $cursor);
        $this->assertCount(1, $cursor->toArray());

        $collection = DB::collection('users')->raw();
        $this->assertInstanceOf(Collection::class, $collection);

        $collection = User::raw();
        $this->assertInstanceOf(Collection::class, $collection);

        $results = DB::collection('users')->whereRaw(['age' => 20])->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Jane Doe', $results[0]['name']);
    }

    public function testPush()
    {
        $id = DB::collection('users')->insertGetId([
            'name' => 'John Doe',
            'tags' => [],
            'messages' => [],
        ]);

        DB::collection('users')->where('_id', $id)->push('tags', 'tag1');

        $user = DB::collection('users')->find($id);
        $this->assertIsArray($user['tags']);
        $this->assertCount(1, $user['tags']);
        $this->assertEquals('tag1', $user['tags'][0]);

        DB::collection('users')->where('_id', $id)->push('tags', 'tag2');
        $user = DB::collection('users')->find($id);
        $this->assertCount(2, $user['tags']);
        $this->assertEquals('tag2', $user['tags'][1]);

        // Add duplicate
        DB::collection('users')->where('_id', $id)->push('tags', 'tag2');
        $user = DB::collection('users')->find($id);
        $this->assertCount(3, $user['tags']);

        // Add unique
        DB::collection('users')->where('_id', $id)->push('tags', 'tag1', true);
        $user = DB::collection('users')->find($id);
        $this->assertCount(3, $user['tags']);

        $message = ['from' => 'Jane', 'body' => 'Hi John'];
        DB::collection('users')->where('_id', $id)->push('messages', $message);
        $user = DB::collection('users')->find($id);
        $this->assertIsArray($user['messages']);
        $this->assertCount(1, $user['messages']);
        $this->assertEquals($message, $user['messages'][0]);

        // Raw
        DB::collection('users')->where('_id', $id)->push([
            'tags' => 'tag3',
            'messages' => ['from' => 'Mark', 'body' => 'Hi John'],
        ]);
        $user = DB::collection('users')->find($id);
        $this->assertCount(4, $user['tags']);
        $this->assertCount(2, $user['messages']);

        DB::collection('users')->where('_id', $id)->push([
            'messages' => [
                'date' => new DateTime(),
                'body' => 'Hi John',
            ],
        ]);
        $user = DB::collection('users')->find($id);
        $this->assertCount(3, $user['messages']);
    }

    public function testPull()
    {
        $message1 = ['from' => 'Jane', 'body' => 'Hi John'];
        $message2 = ['from' => 'Mark', 'body' => 'Hi John'];

        $id = DB::collection('users')->insertGetId([
            'name' => 'John Doe',
            'tags' => ['tag1', 'tag2', 'tag3', 'tag4'],
            'messages' => [$message1, $message2],
        ]);

        DB::collection('users')->where('_id', $id)->pull('tags', 'tag3');

        $user = DB::collection('users')->find($id);
        $this->assertIsArray($user['tags']);
        $this->assertCount(3, $user['tags']);
        $this->assertEquals('tag4', $user['tags'][2]);

        DB::collection('users')->where('_id', $id)->pull('messages', $message1);

        $user = DB::collection('users')->find($id);
        $this->assertIsArray($user['messages']);
        $this->assertCount(1, $user['messages']);

        // Raw
        DB::collection('users')->where('_id', $id)->pull(['tags' => 'tag2', 'messages' => $message2]);
        $user = DB::collection('users')->find($id);
        $this->assertCount(2, $user['tags']);
        $this->assertCount(0, $user['messages']);
    }

    public function testDistinct()
    {
        DB::collection('items')->insert([
            ['name' => 'knife', 'type' => 'sharp'],
            ['name' => 'fork', 'type' => 'sharp'],
            ['name' => 'spoon', 'type' => 'round'],
            ['name' => 'spoon', 'type' => 'round'],
        ]);

        $items = DB::collection('items')->distinct('name')->get()->toArray();
        sort($items);
        $this->assertCount(3, $items);
        $this->assertEquals(['fork', 'knife', 'spoon'], $items);

        $types = DB::collection('items')->distinct('type')->get()->toArray();
        sort($types);
        $this->assertCount(2, $types);
        $this->assertEquals(['round', 'sharp'], $types);
    }

    public function testCustomId()
    {
        DB::collection('items')->insert([
            ['_id' => 'knife', 'type' => 'sharp', 'amount' => 34],
            ['_id' => 'fork', 'type' => 'sharp', 'amount' => 20],
            ['_id' => 'spoon', 'type' => 'round', 'amount' => 3],
        ]);

        $item = DB::collection('items')->find('knife');
        $this->assertEquals('knife', $item['_id']);

        $item = DB::collection('items')->where('_id', 'fork')->first();
        $this->assertEquals('fork', $item['_id']);

        DB::collection('users')->insert([
            ['_id' => 1, 'name' => 'Jane Doe'],
            ['_id' => 2, 'name' => 'John Doe'],
        ]);

        $item = DB::collection('users')->find(1);
        $this->assertEquals(1, $item['_id']);
    }

    public function testTake()
    {
        DB::collection('items')->insert([
            ['name' => 'knife', 'type' => 'sharp', 'amount' => 34],
            ['name' => 'fork', 'type' => 'sharp', 'amount' => 20],
            ['name' => 'spoon', 'type' => 'round', 'amount' => 3],
            ['name' => 'spoon', 'type' => 'round', 'amount' => 14],
        ]);

        $items = DB::collection('items')->orderBy('name')->take(2)->get();
        $this->assertCount(2, $items);
        $this->assertEquals('fork', $items[0]['name']);
    }

    public function testSkip()
    {
        DB::collection('items')->insert([
            ['name' => 'knife', 'type' => 'sharp', 'amount' => 34],
            ['name' => 'fork', 'type' => 'sharp', 'amount' => 20],
            ['name' => 'spoon', 'type' => 'round', 'amount' => 3],
            ['name' => 'spoon', 'type' => 'round', 'amount' => 14],
        ]);

        $items = DB::collection('items')->orderBy('name')->skip(2)->get();
        $this->assertCount(2, $items);
        $this->assertEquals('spoon', $items[0]['name']);
    }

    public function testPluck()
    {
        DB::collection('users')->insert([
            ['name' => 'Jane Doe', 'age' => 20],
            ['name' => 'John Doe', 'age' => 25],
        ]);

        $age = DB::collection('users')->where('name', 'John Doe')->pluck('age')->toArray();
        $this->assertEquals([25], $age);
    }

    public function testList()
    {
        DB::collection('items')->insert([
            ['name' => 'knife', 'type' => 'sharp', 'amount' => 34],
            ['name' => 'fork', 'type' => 'sharp', 'amount' => 20],
            ['name' => 'spoon', 'type' => 'round', 'amount' => 3],
            ['name' => 'spoon', 'type' => 'round', 'amount' => 14],
        ]);

        $list = DB::collection('items')->pluck('name')->toArray();
        sort($list);
        $this->assertCount(4, $list);
        $this->assertEquals(['fork', 'knife', 'spoon', 'spoon'], $list);

        $list = DB::collection('items')->pluck('type', 'name')->toArray();
        $this->assertCount(3, $list);
        $this->assertEquals(['knife' => 'sharp', 'fork' => 'sharp', 'spoon' => 'round'], $list);

        $list = DB::collection('items')->pluck('name', '_id')->toArray();
        $this->assertCount(4, $list);
        $this->assertEquals(24, strlen(key($list)));
    }

    public function testAggregate()
    {
        DB::collection('items')->insert([
            ['name' => 'knife', 'type' => 'sharp', 'amount' => 34],
            ['name' => 'fork', 'type' => 'sharp', 'amount' => 20],
            ['name' => 'spoon', 'type' => 'round', 'amount' => 3],
            ['name' => 'spoon', 'type' => 'round', 'amount' => 14],
        ]);

        $this->assertEquals(71, DB::collection('items')->sum('amount'));
        $this->assertEquals(4, DB::collection('items')->count('amount'));
        $this->assertEquals(3, DB::collection('items')->min('amount'));
        $this->assertEquals(34, DB::collection('items')->max('amount'));
        $this->assertEquals(17.75, DB::collection('items')->avg('amount'));

        $this->assertEquals(2, DB::collection('items')->where('name', 'spoon')->count('amount'));
        $this->assertEquals(14, DB::collection('items')->where('name', 'spoon')->max('amount'));
    }

    public function testSubdocumentAggregate()
    {
        DB::collection('items')->insert([
            ['name' => 'knife', 'amount' => ['hidden' => 10, 'found' => 3]],
            ['name' => 'fork', 'amount' => ['hidden' => 35, 'found' => 12]],
            ['name' => 'spoon', 'amount' => ['hidden' => 14, 'found' => 21]],
            ['name' => 'spoon', 'amount' => ['hidden' => 6, 'found' => 4]],
        ]);

        $this->assertEquals(65, DB::collection('items')->sum('amount.hidden'));
        $this->assertEquals(4, DB::collection('items')->count('amount.hidden'));
        $this->assertEquals(6, DB::collection('items')->min('amount.hidden'));
        $this->assertEquals(35, DB::collection('items')->max('amount.hidden'));
        $this->assertEquals(16.25, DB::collection('items')->avg('amount.hidden'));
    }

    public function testSubdocumentArrayAggregate()
    {
        DB::collection('items')->insert([
            ['name' => 'knife', 'amount' => [['hidden' => 10, 'found' => 3], ['hidden' => 5, 'found' => 2]]],
            [
                'name' => 'fork',
                'amount' => [
                    ['hidden' => 35, 'found' => 12],
                    ['hidden' => 7, 'found' => 17],
                    ['hidden' => 1, 'found' => 19],
                ],
            ],
            ['name' => 'spoon', 'amount' => [['hidden' => 14, 'found' => 21]]],
            ['name' => 'teaspoon', 'amount' => []],
        ]);

        $this->assertEquals(72, DB::collection('items')->sum('amount.*.hidden'));
        $this->assertEquals(6, DB::collection('items')->count('amount.*.hidden'));
        $this->assertEquals(1, DB::collection('items')->min('amount.*.hidden'));
        $this->assertEquals(35, DB::collection('items')->max('amount.*.hidden'));
        $this->assertEquals(12, DB::collection('items')->avg('amount.*.hidden'));
    }

    public function testUpsert()
    {
        DB::collection('items')->where('name', 'knife')
            ->update(
                ['amount' => 1],
                ['upsert' => true]
            );

        $this->assertEquals(1, DB::collection('items')->count());

        Item::where('name', 'spoon')
            ->update(
                ['amount' => 1],
                ['upsert' => true]
            );

        $this->assertEquals(2, DB::collection('items')->count());
    }

    public function testUnset()
    {
        $id1 = DB::collection('users')->insertGetId(['name' => 'John Doe', 'note1' => 'ABC', 'note2' => 'DEF']);
        $id2 = DB::collection('users')->insertGetId(['name' => 'Jane Doe', 'note1' => 'ABC', 'note2' => 'DEF']);

        DB::collection('users')->where('name', 'John Doe')->unset('note1');

        $user1 = DB::collection('users')->find($id1);
        $user2 = DB::collection('users')->find($id2);

        $this->assertArrayNotHasKey('note1', $user1);
        $this->assertArrayHasKey('note2', $user1);
        $this->assertArrayHasKey('note1', $user2);
        $this->assertArrayHasKey('note2', $user2);

        DB::collection('users')->where('name', 'Jane Doe')->unset(['note1', 'note2']);

        $user2 = DB::collection('users')->find($id2);
        $this->assertArrayNotHasKey('note1', $user2);
        $this->assertArrayNotHasKey('note2', $user2);
    }

    public function testUpdateSubdocument()
    {
        $id = DB::collection('users')->insertGetId(['name' => 'John Doe', 'address' => ['country' => 'Belgium']]);

        DB::collection('users')->where('_id', $id)->update(['address.country' => 'England']);

        $check = DB::collection('users')->find($id);
        $this->assertEquals('England', $check['address']['country']);
    }

    public function testDates()
    {
        DB::collection('users')->insert([
            ['name' => 'John Doe', 'birthday' => new UTCDateTime(Date::parse("1980-01-01 00:00:00")->format('Uv'))],
            ['name' => 'Jane Doe', 'birthday' => new UTCDateTime(Date::parse("1981-01-01 00:00:00")->format('Uv'))],
            ['name' => 'Robert Roe', 'birthday' => new UTCDateTime(Date::parse("1982-01-01 00:00:00")->format('Uv'))],
            ['name' => 'Mark Moe', 'birthday' => new UTCDateTime(Date::parse("1983-01-01 00:00:00")->format('Uv'))],
        ]);

        $user = DB::collection('users')
            ->where('birthday', new UTCDateTime(Date::parse("1980-01-01 00:00:00")->format('Uv')))
            ->first();
        $this->assertEquals('John Doe', $user['name']);

        $user = DB::collection('users')->where('birthday', '=', new DateTime("1980-01-01 00:00:00"))->first();
        $this->assertEquals('John Doe', $user['name']);

        $start = new UTCDateTime(1000 * strtotime("1981-01-01 00:00:00"));
        $stop = new UTCDateTime(1000 * strtotime("1982-01-01 00:00:00"));

        $users = DB::collection('users')->whereBetween('birthday', [$start, $stop])->get();
        $this->assertCount(2, $users);
    }

    public function testOperators()
    {
        DB::collection('users')->insert([
            ['name' => 'John Doe', 'age' => 30],
            ['name' => 'Jane Doe'],
            ['name' => 'Robert Roe', 'age' => 'thirty-one'],
        ]);

        $results = DB::collection('users')->where('age', 'exists', true)->get();
        $this->assertCount(2, $results);
        $resultsNames = [$results[0]['name'], $results[1]['name']];
        $this->assertContains('John Doe', $resultsNames);
        $this->assertContains('Robert Roe', $resultsNames);

        $results = DB::collection('users')->where('age', 'exists', false)->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Jane Doe', $results[0]['name']);

        $results = DB::collection('users')->where('age', 'type', 2)->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Robert Roe', $results[0]['name']);

        $results = DB::collection('users')->where('age', 'mod', [15, 0])->get();
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results[0]['name']);

        $results = DB::collection('users')->where('age', 'mod', [29, 1])->get();
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results[0]['name']);

        $results = DB::collection('users')->where('age', 'mod', [14, 0])->get();
        $this->assertCount(0, $results);

        DB::collection('items')->insert([
            ['name' => 'fork', 'tags' => ['sharp', 'pointy']],
            ['name' => 'spork', 'tags' => ['sharp', 'pointy', 'round', 'bowl']],
            ['name' => 'spoon', 'tags' => ['round', 'bowl']],
        ]);

        $results = DB::collection('items')->where('tags', 'all', ['sharp', 'pointy'])->get();
        $this->assertCount(2, $results);

        $results = DB::collection('items')->where('tags', 'all', ['sharp', 'round'])->get();
        $this->assertCount(1, $results);

        $results = DB::collection('items')->where('tags', 'size', 2)->get();
        $this->assertCount(2, $results);

        $results = DB::collection('items')->where('tags', '$size', 2)->get();
        $this->assertCount(2, $results);

        $results = DB::collection('items')->where('tags', 'size', 3)->get();
        $this->assertCount(0, $results);

        $results = DB::collection('items')->where('tags', 'size', 4)->get();
        $this->assertCount(1, $results);

        $regex = new Regex(".*doe", "i");
        $results = DB::collection('users')->where('name', 'regex', $regex)->get();
        $this->assertCount(2, $results);

        $regex = new Regex(".*doe", "i");
        $results = DB::collection('users')->where('name', 'regexp', $regex)->get();
        $this->assertCount(2, $results);

        $results = DB::collection('users')->where('name', 'REGEX', $regex)->get();
        $this->assertCount(2, $results);

        $results = DB::collection('users')->where('name', 'regexp', '/.*doe/i')->get();
        $this->assertCount(2, $results);

        $results = DB::collection('users')->where('name', 'not regexp', '/.*doe/i')->get();
        $this->assertCount(1, $results);

        DB::collection('users')->insert([
            [
                'name' => 'John Doe',
                'addresses' => [
                    ['city' => 'Ghent'],
                    ['city' => 'Paris'],
                ],
            ],
            [
                'name' => 'Jane Doe',
                'addresses' => [
                    ['city' => 'Brussels'],
                    ['city' => 'Paris'],
                ],
            ],
        ]);

        $users = DB::collection('users')->where('addresses', 'elemMatch', ['city' => 'Brussels'])->get();
        $this->assertCount(1, $users);
        $this->assertEquals('Jane Doe', $users[0]['name']);
    }

    public function testIncrement()
    {
        DB::collection('users')->insert([
            ['name' => 'John Doe', 'age' => 30, 'note' => 'adult'],
            ['name' => 'Jane Doe', 'age' => 10, 'note' => 'minor'],
            ['name' => 'Robert Roe', 'age' => null],
            ['name' => 'Mark Moe'],
        ]);

        $user = DB::collection('users')->where('name', 'John Doe')->first();
        $this->assertEquals(30, $user['age']);

        DB::collection('users')->where('name', 'John Doe')->increment('age');
        $user = DB::collection('users')->where('name', 'John Doe')->first();
        $this->assertEquals(31, $user['age']);

        DB::collection('users')->where('name', 'John Doe')->decrement('age');
        $user = DB::collection('users')->where('name', 'John Doe')->first();
        $this->assertEquals(30, $user['age']);

        DB::collection('users')->where('name', 'John Doe')->increment('age', 5);
        $user = DB::collection('users')->where('name', 'John Doe')->first();
        $this->assertEquals(35, $user['age']);

        DB::collection('users')->where('name', 'John Doe')->decrement('age', 5);
        $user = DB::collection('users')->where('name', 'John Doe')->first();
        $this->assertEquals(30, $user['age']);

        DB::collection('users')->where('name', 'Jane Doe')->increment('age', 10, ['note' => 'adult']);
        $user = DB::collection('users')->where('name', 'Jane Doe')->first();
        $this->assertEquals(20, $user['age']);
        $this->assertEquals('adult', $user['note']);

        DB::collection('users')->where('name', 'John Doe')->decrement('age', 20, ['note' => 'minor']);
        $user = DB::collection('users')->where('name', 'John Doe')->first();
        $this->assertEquals(10, $user['age']);
        $this->assertEquals('minor', $user['note']);

        DB::collection('users')->increment('age');
        $user = DB::collection('users')->where('name', 'John Doe')->first();
        $this->assertEquals(11, $user['age']);
        $user = DB::collection('users')->where('name', 'Jane Doe')->first();
        $this->assertEquals(21, $user['age']);
        $user = DB::collection('users')->where('name', 'Robert Roe')->first();
        $this->assertNull($user['age']);
        $user = DB::collection('users')->where('name', 'Mark Moe')->first();
        $this->assertEquals(1, $user['age']);
    }

    public function testProjections()
    {
        DB::collection('items')->insert([
            ['name' => 'fork', 'tags' => ['sharp', 'pointy']],
            ['name' => 'spork', 'tags' => ['sharp', 'pointy', 'round', 'bowl']],
            ['name' => 'spoon', 'tags' => ['round', 'bowl']],
        ]);

        $results = DB::collection('items')->project(['tags' => ['$slice' => 1]])->get();

        foreach ($results as $result) {
            $this->assertEquals(1, count($result['tags']));
        }
    }

    public function testValue()
    {
        DB::collection('books')->insert([
            ['title' => 'Moby-Dick', 'author' => ['first_name' => 'Herman', 'last_name' => 'Melville']],
        ]);

        $this->assertEquals('Moby-Dick', DB::collection('books')->value('title'));
        $this->assertEquals(['first_name' => 'Herman', 'last_name' => 'Melville'], DB::collection('books')
            ->value('author'));
        $this->assertEquals('Herman', DB::collection('books')->value('author.first_name'));
        $this->assertEquals('Melville', DB::collection('books')->value('author.last_name'));
    }

    public function testHintOptions()
    {
        DB::collection('items')->insert([
            ['name' => 'fork',  'tags' => ['sharp', 'pointy']],
            ['name' => 'spork', 'tags' => ['sharp', 'pointy', 'round', 'bowl']],
            ['name' => 'spoon', 'tags' => ['round', 'bowl']],
        ]);

        $results = DB::collection('items')->hint(['$natural' => -1])->get();

        $this->assertEquals('spoon', $results[0]['name']);
        $this->assertEquals('spork', $results[1]['name']);
        $this->assertEquals('fork', $results[2]['name']);

        $results = DB::collection('items')->hint(['$natural' => 1])->get();

        $this->assertEquals('spoon', $results[2]['name']);
        $this->assertEquals('spork', $results[1]['name']);
        $this->assertEquals('fork', $results[0]['name']);
    }

    public function testCursor()
    {
        $data = [
            ['name' => 'fork', 'tags' => ['sharp', 'pointy']],
            ['name' => 'spork', 'tags' => ['sharp', 'pointy', 'round', 'bowl']],
            ['name' => 'spoon', 'tags' => ['round', 'bowl']],
        ];
        DB::collection('items')->insert($data);

        $results = DB::collection('items')->orderBy('_id', 'asc')->cursor();

        $this->assertInstanceOf(LazyCollection::class, $results);
        $i = 0;
        foreach ($results as $result) {
            $this->assertEquals($data[$i]['name'], $result['name']);
            $i++;
        }
    }
}
