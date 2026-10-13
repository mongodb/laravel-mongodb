<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Ticket;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Laravel\Eloquent\DocumentModel;
use MongoDB\Laravel\Tests\TestCase;

use function get_debug_type;

/**
 * Mass update() must store date-cast attributes as UTCDateTime, same as save().
 *
 * @see https://github.com/mongodb/laravel-mongodb/issues/2177
 */
class GH2177Test extends TestCase
{
    private const TYPE_MAP = ['root' => 'array', 'document' => 'array', 'array' => 'array'];

    public function tearDown(): void
    {
        GH2177Event::truncate();

        parent::tearDown();
    }

    public function testMassUpdateWithCarbonStoresUtcDateTime(): void
    {
        GH2177Event::create(['name' => 'a', 'match' => true, 'end_date' => Carbon::parse('2020-01-01')]);

        GH2177Event::where('match', true)->update([
            'end_date' => Carbon::parse('2021-01-08 10:00'),
        ]);

        $doc = $this->rawDocument('a');

        $this->assertInstanceOf(UTCDateTime::class, $doc['end_date']);
        $this->assertSame(
            '2021-01-08T10:00:00+00:00',
            $doc['end_date']->toDateTime()->format('c'),
        );
    }

    public function testMassUpdateWithIsoStringStoresUtcDateTimeWhenCast(): void
    {
        GH2177Event::create(['name' => 'b', 'match' => true, 'end_date' => Carbon::parse('2020-01-01')]);

        GH2177Event::where('match', true)->update(['end_date' => '2021-01-08T10:00:00+00:00']);

        $doc = $this->rawDocument('b');

        $this->assertInstanceOf(
            UTCDateTime::class,
            $doc['end_date'],
            'ISO string on a datetime-cast field must become UTCDateTime, not a string; got ' . get_debug_type($doc['end_date']),
        );
        $this->assertSame(
            '2021-01-08T10:00:00+00:00',
            $doc['end_date']->toDateTime()->format('c'),
        );
    }

    public function testMassUpdateViaSetOperatorCastsDates(): void
    {
        GH2177Event::create(['name' => 'c', 'match' => true, 'end_date' => Carbon::parse('2020-01-01')]);

        GH2177Event::where('match', true)->update([
            '$set' => ['end_date' => '2022-02-02 15:30:00'],
        ]);

        $doc = $this->rawDocument('c');

        $this->assertInstanceOf(UTCDateTime::class, $doc['end_date']);
        $this->assertSame(
            '2022-02-02T15:30:00+00:00',
            $doc['end_date']->toDateTime()->format('c'),
        );
    }

    public function testSaveStillStoresUtcDateTime(): void
    {
        $event = GH2177Event::create(['name' => 'd', 'match' => true]);
        $event->end_date = Carbon::parse('2023-03-03 12:00');
        $event->save();

        $doc = $this->rawDocument('d');

        $this->assertInstanceOf(UTCDateTime::class, $doc['end_date']);
    }

    public function testMassUpdateNullClearsDate(): void
    {
        GH2177Event::create(['name' => 'e', 'match' => true, 'end_date' => Carbon::parse('2020-01-01')]);

        GH2177Event::where('match', true)->update(['end_date' => null]);

        $doc = $this->rawDocument('e');

        $this->assertArrayHasKey('end_date', $doc);
        $this->assertNull($doc['end_date']);
    }

    public function testUncastStringFieldRemainsString(): void
    {
        GH2177Event::create(['name' => 'f', 'match' => true, 'label' => 'old']);

        GH2177Event::where('match', true)->update(['label' => '2021-01-08T10:00:00+00:00']);

        $doc = $this->rawDocument('f');

        $this->assertIsString($doc['label']);
        $this->assertSame('2021-01-08T10:00:00+00:00', $doc['label']);
    }

    public function testInstanceUpdateGoesThroughFillAndSave(): void
    {
        $event = GH2177Event::create(['name' => 'g', 'match' => true]);
        $event->update(['end_date' => '2024-04-04T08:00:00+00:00']);

        $doc = $this->rawDocument('g');

        $this->assertInstanceOf(UTCDateTime::class, $doc['end_date']);
    }

    /** @return array<string, mixed> */
    private function rawDocument(string $name): array
    {
        $collection = GH2177Event::query()->getConnection()->getCollection('gh2177_events');

        $doc = $collection->findOne(['name' => $name], ['typeMap' => self::TYPE_MAP]);

        $this->assertIsArray($doc);

        return $doc;
    }
}

class GH2177Event extends Model
{
    use DocumentModel;

    protected $keyType = 'string';
    protected $connection = 'mongodb';
    protected $table = 'gh2177_events';
    protected static $unguarded = true;

    protected $casts = [
        'end_date' => 'datetime',
        'match' => 'boolean',
    ];
}
