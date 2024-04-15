<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Concert;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Laravel\Tests\TestCase;

use function count;
use function in_array;

class WriteOperationsTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testModelInsert(): void
    {
        require_once __DIR__ . '/Concert.php';

        Concert::truncate();

        // begin model insert one
        $concert = new Concert();
        $concert->performer = 'Mitsuko Uchida';
        $concert->venue = 'Carnegie Hall';
        $concert->genres = ['classical'];
        $concert->ticketsSold = 2121;
        $concert->performanceDate = Carbon::create(2024, 4, 1, 20, 0, 0, 'EST');
        $concert->save();
        // end model insert one

        // begin inserted id
        $insertedId = $concert->id;
        // end inserted id

        $this->assertNotNull($concert);
        $this->assertNotNull($insertedId);

        $result = Concert::first();
        $this->assertInstanceOf(Concert::class, $result);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testModelInsertMassAssign(): void
    {
        require_once __DIR__ . '/Concert.php';

        Concert::truncate();

        // begin model insert one mass assign
        $insertResult = Concert::create([
            'performer' => 'The Rolling Stones',
            'venue' => 'Soldier Field',
            'genres' => [ 'rock', 'pop', 'blues' ],
            'ticketsSold' => 59527,
            'performanceDate' => Carbon::create(2024, 6, 30, 20, 0, 0, 'CDT'),
        ]);
        // end model insert one mass assign

        $this->assertNotNull($insertResult);

        $result = Concert::first();
        $this->assertInstanceOf(Concert::class, $result);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testModelInsertMany(): void
    {
        require_once __DIR__ . '/Concert.php';

        Concert::truncate();

        // begin model insert many
        $data = [
            [
                'performer' => 'Brad Mehldau',
                'venue' => 'Philharmonie de Paris',
                'genres' => [ 'jazz', 'post-bop' ],
                'ticketsSold' => 5745,
                'performanceDate' => new UTCDateTime(Carbon::create(2025, 2, 12, 20, 0, 0, 'CET')),
            ],
            [
                'performer' => 'Billy Joel',
                'venue' => 'Madison Square Garden',
                'genres' => [ 'rock', 'soft rock', 'pop rock' ],
                'ticketsSold' => 12852,
                'performanceDate' => new UTCDateTime(Carbon::create(2025, 2, 12, 20, 0, 0, 'CET')),
            ],
        ];

        Concert::insert($data);
        // end model insert many

        $results = Concert::get();

        $this->assertEquals(2, count($results));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testModelUpdateSave(): void
    {
        require_once __DIR__ . '/Concert.php';
        Concert::truncate();

        // insert the model
        Concert::create([
            'performer' => 'Brad Mehldau',
            'venue' => 'Philharmonie de Paris',
            'genres' => [ 'jazz', 'post-bop' ],
            'ticketsSold' => 5745,
            'performanceDate' => new UTCDateTime(Carbon::create(2025, 2, 12, 20, 0, 0, 'CET')),
        ]);

        // begin model update one save
        $concert = Concert::first();
        $concert->venue = 'Manchester Arena';
        $concert->ticketsSold = 9543;
        $concert->save();
        // end model update one save

        $result = Concert::first();
        $this->assertInstanceOf(Concert::class, $result);

        $this->assertNotNull($result);
        $this->assertEquals('Manchester Arena', $result->venue);
        $this->assertEquals('Brad Mehldau', $result->performer);
        $this->assertEquals(9543, $result->ticketsSold);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testModelUpdateFluent(): void
    {
        require_once __DIR__ . '/Concert.php';
        Concert::truncate();

        // insert the model
        Concert::create([
            'performer' => 'Brad Mehldau',
            'venue' => 'Philharmonie de Paris',
            'genres' => [ 'jazz', 'post-bop' ],
            'ticketsSold' => 5745,
            'performanceDate' => new UTCDateTime(Carbon::create(2025, 2, 12, 20, 0, 0, 'CET')),
        ]);

        // begin model update one fluent
        $concert = Concert::where(['performer' => 'Brad Mehldau'])
            ->orderBy('_id')
            ->first()
            ->update(['venue' => 'Manchester Arena', 'ticketsSold' => 9543]);
        // end model update one fluent

        $result = Concert::first();
        $this->assertInstanceOf(Concert::class, $result);

        $this->assertNotNull($result);
        $this->assertEquals('Manchester Arena', $result->venue);
        $this->assertEquals('Brad Mehldau', $result->performer);
        $this->assertEquals(9543, $result->ticketsSold);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testModelUpdateMultiple(): void
    {
        require_once __DIR__ . '/Concert.php';
        Concert::truncate();

        // insert the model
        Concert::create([
            'performer' => 'Brad Mehldau',
            'venue' => 'Philharmonie de Paris',
            'genres' => [ 'jazz', 'post-bop' ],
            'ticketsSold' => 5745,
            'performanceDate' => new UTCDateTime(Carbon::create(2025, 2, 12, 20, 0, 0, 'CET')),
        ]);

        Concert::create([
            'performer' => 'The Rolling Stones',
            'venue' => 'Soldier Field',
            'genres' => [ 'rock', 'pop', 'blues' ],
            'ticketsSold' => 59527,
            'performanceDate' => Carbon::create(2024, 6, 30, 20, 0, 0, 'CDT'),
        ]);
        // begin model update multiple
        Concert::whereIn('venue', ['Philharmonie de Paris', 'Soldier Field'])
            ->update(['venue' => 'Concertgebouw', 'ticketsSold' => 0]);
        // end model update multiple

        $results = Concert::get();

        foreach ($results as $result) {
            $this->assertInstanceOf(Concert::class, $result);

            $this->assertNotNull($result);
            $this->assertEquals('Concertgebouw', $result->venue);
            $this->assertEquals(0, $result->ticketsSold);
        }
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testModelUpsert(): void
    {
        require_once __DIR__ . '/Concert.php';
        Concert::truncate();

        // begin model upsert
        Concert::where(['performer' => 'Jon Batiste', 'venue' => 'Radio City Music Hall'])
            ->update(
                ['genres' => ['R&B', 'soul'], 'ticketsSold' => 4000],
                ['upsert' => true],
            );
        // end model upsert

        $result = Concert::first();

        $this->assertInstanceOf(Concert::class, $result);
        $this->assertEquals('Jon Batiste', $result->performer);
        $this->assertEquals(4000, $result->ticketsSold);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testModelPushArray(): void
    {
        require_once __DIR__ . '/Concert.php';
        Concert::truncate();

        // begin array example document
        Concert::create([
            'performer' => 'Mitsuko Uchida',
            'genres' => ['classical', 'dance-pop'],
        ]);
        // end array example document

        // begin model array push
        Concert::where('performer', 'Mitsuko Uchida')
            ->push(
                'genres',
                ['baroque'],
            );
        // end model array push

        $result = Concert::first();

        $this->assertInstanceOf(Concert::class, $result);
        $this->assertContains('baroque', $result->genres);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testModelPullArray(): void
    {
        require_once __DIR__ . '/Concert.php';
        Concert::truncate();

        Concert::create([
            'performer' => 'Mitsuko Uchida',
            'genres' => [ 'classical', 'dance-pop' ],
        ]);

        // begin model array pull
        Concert::where('performer', 'Mitsuko Uchida')
            ->pull(
                'genres',
                ['dance-pop', 'classical'],
            );
        // end model array pull

        $result = Concert::first();

        $this->assertInstanceOf(Concert::class, $result);
        $this->assertEmpty($result->genres);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testModelPositional(): void
    {
        require_once __DIR__ . '/Concert.php';
        Concert::truncate();

        Concert::create([
            'performer' => 'Mitsuko Uchida',
            'genres' => [ 'classical', 'dance-pop' ],
        ]);

        // begin model array positional
        $match = ['performer' => 'Mitsuko Uchida', 'genres' => 'dance-pop'];
        $update = ['$set' => ['genres.$' => 'contemporary']];

        DB::connection('mongodb')
            ->getCollection('concerts')
            ->updateOne($match, $update);
        // end model array positional

        $result = Concert::first();

        $this->assertInstanceOf(Concert::class, $result);
        $this->assertContains('classical', $result->genres);
        $this->assertContains('contemporary', $result->genres);
        $this->assertFalse(in_array('dance-pop', $result->genres));
    }

    public function testModelDeleteById(): void
    {
        require_once __DIR__ . '/Concert.php';
        Concert::truncate();

        $data = [
            [
                '_id' => 'CH-0401242000',
                'performer' => 'Mitsuko Uchida',
                'venue' => 'Carnegie Hall',
                'genres' => ['classical'],
                'ticketsSold' => 2121,
                'performanceDate' => new UTCDateTime(Carbon::create(2024, 4, 1, 20, 0, 0, 'EST')),
            ],
            [
                '_id' => 'MSG-0212252000',
                'performer' => 'Brad Mehldau',
                'venue' => 'Philharmonie de Paris',
                'genres' => [ 'jazz', 'post-bop' ],
                'ticketsSold' => 5745,
                'performanceDate' => new UTCDateTime(Carbon::create(2025, 2, 12, 20, 0, 0, 'CET')),
            ],
            [
                '_id' => 'MSG-021222000',
                'performer' => 'Billy Joel',
                'venue' => 'Madison Square Garden',
                'genres' => [ 'rock', 'soft rock', 'pop rock' ],
                'ticketsSold' => 12852,
                'performanceDate' => new UTCDateTime(Carbon::create(2025, 2, 12, 20, 0, 0, 'CET')),
            ],
            [
                '_id' => 'SF-06302000',
                'performer' => 'The Rolling Stones',
                'venue' => 'Soldier Field',
                'genres' => [ 'rock', 'pop', 'blues' ],
                'ticketsSold' => 59527,
                'performanceDate' => new UTCDateTime(Carbon::create(2024, 6, 30, 20, 0, 0, 'CDT')),
            ],
        ];
        Concert::insert($data);

        $this->assertEquals(4, Concert::count());

        $id = Concert::first()->id;

        // begin model delete by id
        $id = 'MSG-0212252000';
        Concert::destroy($id);
        // end model delete by id

        $this->assertEquals(3, Concert::count());
        $this->assertNull(Concert::find($id));
    }

    public function testModelDeleteModel(): void
    {
        require_once __DIR__ . '/Concert.php';
        Concert::truncate();

        $data = [
            [
                'performer' => 'Mitsuko Uchida',
                'venue' => 'Carnegie Hall',
            ],
        ];
        Concert::insert($data);

        // begin delete one model
        $concert = Concert::first();
        $concert->delete();
        // end delete one model

        $this->assertEquals(0, Concert::count());
    }

    public function testModelDeleteFirst(): void
    {
        require_once __DIR__ . '/Concert.php';
        Concert::truncate();

        $data = [
            [
                'performer' => 'Mitsuko Uchida',
                'venue' => 'Carnegie Hall',
            ],
            [
                'performer' => 'Brad Mehldau',
                'venue' => 'Philharmonie de Paris',
            ],
            [
                'performer' => 'Billy Joel',
                'venue' => 'Madison Square Garden',
            ],
            [
                'performer' => 'The Rolling Stones',
                'venue' => 'Soldier Field',
            ],
        ];
        Concert::insert($data);

        // begin model delete one fluent
        Concert::where('venue', 'Carnegie Hall')
            ->limit(1)
            ->delete();
        // end model delete one fluent

        $this->assertEquals(3, Concert::count());
    }

    public function testModelDeleteMultipleById(): void
    {
        require_once __DIR__ . '/Concert.php';
        Concert::truncate();
        $data = [
            [
                '_id' => 3,
                'performer' => 'Mitsuko Uchida',
                'venue' => 'Carnegie Hall',
            ],
            [
                '_id' => 5,
                'performer' => 'Brad Mehldau',
                'venue' => 'Philharmonie de Paris',
            ],
            [
                '_id' => 7,
                'performer' => 'Billy Joel',
                'venue' => 'Madison Square Garden',
            ],
            [
                '_id' => 9,
                'performer' => 'The Rolling Stones',
                'venue' => 'Soldier Field',
            ],
        ];
        Concert::insert($data);

        // begin model delete multiple by id
        $ids = [3, 5, 7, 9];
        Concert::destroy($ids);
        // end model delete multiple by id

        $this->assertEquals(0, Concert::count());
    }

    public function testModelDeleteMultiple(): void
    {
        require_once __DIR__ . '/Concert.php';
        Concert::truncate();

        $data = [
            [
                'performer' => 'Mitsuko Uchida',
                'venue' => 'Carnegie Hall',
                'genres' => ['classical'],
                'ticketsSold' => 2121,
            ],
            [
                'performer' => 'Brad Mehldau',
                'venue' => 'Philharmonie de Paris',
                'genres' => [ 'jazz', 'post-bop' ],
                'ticketsSold' => 5745,
            ],
            [
                'performer' => 'Billy Joel',
                'venue' => 'Madison Square Garden',
                'genres' => [ 'rock', 'soft rock', 'pop rock' ],
                'ticketsSold' => 12852,
            ],
            [
                'performer' => 'The Rolling Stones',
                'venue' => 'Soldier Field',
                'genres' => [ 'rock', 'pop', 'blues' ],
                'ticketsSold' => 59527,
            ],
        ];
        Concert::insert($data);

        // begin model delete multiple fluent
        Concert::where('ticketsSold', '>', 7500)
            ->delete();
        // end model delete multiple fluent

        $this->assertEquals(2, Concert::count());
    }
}
