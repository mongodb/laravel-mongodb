<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Ticket;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Laravel\Eloquent\DocumentModel;
use MongoDB\Laravel\Relations\EmbedsMany;
use MongoDB\Laravel\Relations\EmbedsOne;
use MongoDB\Laravel\Tests\TestCase;

use function array_column;
use function array_keys;
use function array_map;
use function json_decode;
use function strtoupper;

use const JSON_THROW_ON_ERROR;

/**
 * Embedded documents must be serialized through their model, so that the casts,
 * accessors, hidden attributes and appends of the embedded model apply, and so
 * that BSON values are not exposed as {"$oid": ...} or {"$date": ...}.
 *
 * @see https://github.com/mongodb/laravel-mongodb/issues/2762
 */
class GH2762Test extends TestCase
{
    public function tearDown(): void
    {
        GH2762User::truncate();

        parent::tearDown();
    }

    public function testEmbeddedModelsAreSerializedWithTheirCastsWithoutLoadingTheRelation(): void
    {
        $user = GH2762User::create(['email' => 'florian@example.com']);
        $user->books()->save(new GH2762Book(['title' => 'mongodb', 'author' => 'florian', 'published_at' => new DateTimeImmutable('2020-01-02 03:04:05')]));
        $user->info()->save(new GH2762Info(['gender' => 'M', 'birth_date' => new DateTimeImmutable('1988-12-20'), 'secret' => 'hidden']));

        $fresh = GH2762User::find($user->id);
        $this->assertFalse($fresh->relationLoaded('books'));
        $this->assertFalse($fresh->relationLoaded('info'));

        $array = $fresh->toArray();

        // Relations are not loaded as a side effect of the serialization
        $this->assertFalse($fresh->relationLoaded('books'));
        $this->assertFalse($fresh->relationLoaded('info'));

        $this->assertCount(1, $array['books']);
        $book = $array['books'][0];
        $this->assertSame('mongodb', $book['title']);
        $this->assertSame('2020-01-02', $book['published_at'], 'The "datetime:Y-m-d" cast of the embedded model is applied');
        $this->assertIsString($book['id'], 'The embedded ObjectId is serialized as a string');
        $this->assertSame((string) $user->books->first()->_id, $book['id']);
        $this->assertSame('MONGODB', $book['upper_title'], 'The appends of the embedded model are added');
        $this->assertIsString($book['created_at']);

        $info = $array['info'];
        $this->assertSame('M', $info['gender']);
        $this->assertSame('1988-12-20T00:00:00.000000Z', $info['birth_date']);
        $this->assertIsString($info['id']);
        $this->assertArrayNotHasKey('secret', $info, 'The hidden attributes of the embedded model are removed');
        $this->assertEqualsCanonicalizing(
            ['gender', 'birth_date', 'created_at', 'updated_at', 'id'],
            array_keys($info),
            'The parent model is not serialized in the embedded model',
        );

        // Same output as when the relations are loaded
        $loaded = GH2762User::with('books', 'info')->find($user->id);
        $this->assertSame($loaded->toArray(), $array);

        // Same output for JSON
        $json = json_decode($fresh->toJson(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($array, $json);
        $this->assertSame($book['id'], $json['books'][0]['id']);
    }

    public function testNestedEmbeddedModelsAreSerialized(): void
    {
        $user = GH2762User::create(['email' => 'nested@example.com']);
        $book = $user->books()->save(new GH2762Book(['title' => 'nested', 'published_at' => new DateTimeImmutable('2021-06-07')]));
        $book->reviews()->save(new GH2762Review(['rating' => 5, 'reviewed_at' => new DateTimeImmutable('2021-07-08 09:10:11')]));

        $array = GH2762User::find($user->id)->toArray();

        $review = $array['books'][0]['reviews'][0];
        $this->assertSame(5, $review['rating']);
        $this->assertSame('2021-07-08 09:10', $review['reviewed_at']);
        $this->assertIsString($review['id']);
    }

    public function testRawDocumentsAreSerializedThroughTheEmbeddedModel(): void
    {
        // Documents written outside of Eloquent, with native BSON types
        $bookId = new ObjectId();
        GH2762User::insert([
            [
                'email' => 'raw@example.com',
                'books' => [['_id' => $bookId, 'title' => 'raw', 'published_at' => new UTCDateTime(new DateTimeImmutable('2019-03-04'))]],
                'info' => ['_id' => new ObjectId(), 'gender' => 'F', 'birth_date' => new UTCDateTime(new DateTimeImmutable('1990-01-01'))],
            ],
        ]);

        $array = GH2762User::first()->toArray();

        $this->assertSame((string) $bookId, $array['books'][0]['id']);
        $this->assertSame('2019-03-04', $array['books'][0]['published_at']);
        $this->assertSame('1990-01-01T00:00:00.000000Z', $array['info']['birth_date']);
    }

    public function testLoadedRelationTakesPrecedenceOverStoredAttributes(): void
    {
        $user = GH2762User::create(['email' => 'loaded@example.com']);
        $user->books()->save(new GH2762Book(['title' => 'stored', 'published_at' => new DateTimeImmutable('2020-01-01')]));

        $fresh = GH2762User::find($user->id);
        // Unsaved in-memory change on the loaded relation
        $fresh->books->first()->title = 'modified';

        $this->assertSame('modified', $fresh->attributesToArray()['books'][0]['title']);
        $this->assertSame('modified', $fresh->toArray()['books'][0]['title']);
    }

    public function testUserCustomizationOfTheEmbeddedAttributeIsPreserved(): void
    {
        $user = GH2762UserWithAccessor::create(['email' => 'accessor@example.com']);
        $user->books()->save(new GH2762Book(['title' => 'a', 'published_at' => new DateTimeImmutable('2020-01-01')]));
        $user->books()->save(new GH2762Book(['title' => 'b', 'published_at' => new DateTimeImmutable('2020-01-02')]));

        $array = GH2762UserWithAccessor::find($user->id)->toArray();

        $this->assertSame(['a', 'b'], $array['books']);
    }

    public function testMissingAndHiddenEmbeddedAttributes(): void
    {
        $user = GH2762User::create(['email' => 'empty@example.com', 'info' => null]);

        $array = GH2762User::find($user->id)->toArray();

        $this->assertArrayNotHasKey('books', $array);
        $this->assertNull($array['info']);

        $user->books()->save(new GH2762Book(['title' => 'hidden', 'published_at' => new DateTimeImmutable('2020-01-01')]));
        $array = GH2762User::find($user->id)->makeHidden('books')->toArray();

        $this->assertArrayNotHasKey('books', $array);

        // Plain arrays that are not embedded relations are left untouched
        $user = GH2762User::create(['email' => 'tags@example.com', 'tags' => ['a', 'b']]);
        $this->assertSame(['a', 'b'], GH2762User::find($user->id)->toArray()['tags']);
    }

    public function testAttributesNamedLikeNonRelationMethodsAreLeftUntouched(): void
    {
        $user = GH2762User::create(['email' => 'methods@example.com', 'scores' => [1, 2], 'labels' => ['x']]);

        $array = GH2762User::find($user->id)->toArray();

        $this->assertSame([1, 2], $array['scores']);
        $this->assertSame(['x'], $array['labels']);
    }
}

class GH2762User extends Model
{
    use DocumentModel;

    protected $keyType = 'string';
    protected $connection = 'mongodb';
    protected $table = 'gh2762_users';
    protected static $unguarded = true;

    public function books(): EmbedsMany
    {
        return $this->embedsMany(GH2762Book::class);
    }

    public function info(): EmbedsOne
    {
        return $this->embedsOne(GH2762Info::class);
    }

    /** Not a relation: requires an argument, must not be called during serialization */
    public function scores(int $multiplier): array
    {
        return array_map(static fn (int $score) => $score * $multiplier, $this->attributes['scores'] ?? []);
    }

    /** Not a relation: returns a builtin type, must not be called during serialization */
    public function labels(): string
    {
        throw new LogicException('labels() must not be called during serialization');
    }
}

class GH2762UserWithAccessor extends GH2762User
{
    /** A user-defined accessor on the embedded attribute takes precedence over the default serialization. */
    public function getBooksAttribute(?array $books): array
    {
        return array_column($books ?? [], 'title');
    }
}

class GH2762Book extends Model
{
    use DocumentModel;

    protected $keyType = 'string';
    protected $connection = 'mongodb';
    protected static $unguarded = true;
    protected $casts = ['published_at' => 'datetime:Y-m-d'];
    protected $appends = ['upper_title'];

    public function reviews(): EmbedsMany
    {
        return $this->embedsMany(GH2762Review::class);
    }

    protected function upperTitle(): Attribute
    {
        return Attribute::get(fn () => strtoupper((string) $this->title));
    }
}

class GH2762Review extends Model
{
    use DocumentModel;

    protected $keyType = 'string';
    protected $connection = 'mongodb';
    protected static $unguarded = true;
    protected $casts = ['reviewed_at' => 'immutable_datetime:Y-m-d H:i'];
}

class GH2762Info extends Model
{
    use DocumentModel;

    protected $keyType = 'string';
    protected $connection = 'mongodb';
    protected static $unguarded = true;
    protected $casts = ['birth_date' => 'immutable_datetime'];
    protected $hidden = ['secret'];
}
