<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Eloquent;

use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;
use MongoDB\BSON\Binary;
use MongoDB\BSON\ObjectId;
use MongoDB\Driver\Monitoring\CommandFailedEvent;
use MongoDB\Driver\Monitoring\CommandStartedEvent;
use MongoDB\Driver\Monitoring\CommandSubscriber;
use MongoDB\Driver\Monitoring\CommandSucceededEvent;
use MongoDB\Laravel\Relations\EmbedsMany;
use MongoDB\Laravel\Relations\EmbedsOne;
use MongoDB\Laravel\Tests\Models\Address;
use MongoDB\Laravel\Tests\Models\Book;
use MongoDB\Laravel\Tests\Models\User;
use MongoDB\Laravel\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use stdClass;

use function array_key_first;
use function array_map;

class EagerLoadedEmbeddedConstraintsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->skipIfDocumentsLookupIsNotSupported();

        User::truncate();
        Book::truncate();
    }

    protected function tearDown(): void
    {
        User::truncate();
        Book::truncate();

        parent::tearDown();
    }

    public function testTheConstraintsApplyToTheEagerLoadedEmbeddedDocuments(): void
    {
        $this->seedUsers();

        $users = User::with(['addresses' => fn ($query) => $query->where('city', 'Paris')])->orderBy('name')->get();

        $this->assertSame(['Alice' => ['Paris'], 'Bob' => ['Paris'], 'Carol' => []], self::citiesByName($users));
    }

    public function testTheFilteredEmbeddedDocumentsKeepTheirStoredOrder(): void
    {
        $this->seedUsers();

        $users = User::with(['addresses' => fn ($query) => $query->where('floor', '>', 0)])->orderBy('name')->get();

        $this->assertSame(['Alice' => ['Paris', 'Lyon'], 'Bob' => ['Paris'], 'Carol' => []], self::citiesByName($users));
    }

    public function testTheConstraintsApplyThroughWithWhereHas(): void
    {
        $this->seedUsers();

        $users = User::withWhereHas('addresses', fn ($query) => $query->where('city', 'Lyon'))->get();

        $this->assertSame(['Alice'], $users->pluck('name')->all());
        $this->assertSame(['Lyon'], $users[0]->addresses->pluck('city')->all());
    }

    public function testTheEagerLoadedEmbeddedDocumentsKeepTheRequestedOrder(): void
    {
        $this->seedUsers();

        $alice = User::with(['addresses' => fn ($query) => $query->orderBy('floor', 'desc')])->where('name', 'Alice')->first();

        $this->assertSame([2, 1], $alice->addresses->pluck('floor')->all());
    }

    public function testTheConstraintsApplyToAnEagerLoadedEmbeddedOneRelation(): void
    {
        $alice = User::create(['name' => 'Alice']);
        $alice->father()->create(['name' => 'Alfred']);
        $bob = User::create(['name' => 'Bob']);
        $bob->father()->create(['name' => 'Bertrand']);

        $users = User::with(['father' => fn ($query) => $query->where('name', 'Alfred')])->orderBy('name')->get();

        $this->assertSame(['Alfred', null], $users->map(fn ($user) => $user->father?->name)->all());
    }

    public function testAMatchingEmbeddedDocumentStaysAttachedToItsParent(): void
    {
        $this->seedUsers();

        $address = User::with(['addresses' => fn ($query) => $query->where('city', 'Lyon')])->where('name', 'Alice')->first()->addresses->first();
        $address->update(['floor' => 9]);

        $this->assertSame([1, 9], User::where('name', 'Alice')->first()->addresses->pluck('floor')->all());
    }

    public function testWithoutConstraintsEveryEmbeddedDocumentIsLoaded(): void
    {
        $this->seedUsers();

        $alice = User::with('addresses')->where('name', 'Alice')->first();

        $this->assertSame(['Paris', 'Lyon'], $alice->addresses->pluck('city')->all());
    }

    public function testALimitAppliesToTheDocumentsOfEachParent(): void
    {
        $this->seedUsers();

        $users = User::with(['addresses' => fn ($query) => $query->orderBy('floor', 'desc')->limit(1)])->orderBy('name')->get();

        $this->assertSame([['Lyon'], ['Paris'], []], $users->map(fn ($user) => $user->addresses->pluck('city')->all())->all());
    }

    public function testConstraintsOnANestedEmbeddedRelationAreNotSupported(): void
    {
        $this->seedUsers();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Constraints on an eager loaded nested embedded relation are not supported');

        User::with('addresses')->where('name', 'Alice')->first()->addresses->load(['addresses' => fn ($query) => $query->where('floor', 1)]);
    }

    public function testConstraintsApplyAlongsideAnotherEmbeddedRelation(): void
    {
        $this->seedUsers();
        User::where('name', 'Alice')->firstOrFail()->father()->create(['name' => 'Alfred']);

        $alice = User::with(['father', 'addresses' => fn ($query) => $query->where('city', 'Paris')])->where('name', 'Alice')->firstOrFail();

        $this->assertSame('Alfred', $alice->father->name);
        $this->assertSame(['Paris'], $alice->addresses->pluck('city')->all());
    }

    public function testConstraintsApplyWhenReloadingTheRelationOfLoadedUsers(): void
    {
        $this->seedUsers();

        $users = User::with('addresses')->orderBy('name')->get()->load(['addresses' => fn ($query) => $query->where('city', 'Lyon')]);

        $this->assertSame(['Alice' => ['Lyon'], 'Bob' => [], 'Carol' => []], self::citiesByName($users));
    }

    public function testTheOrderOnTheEmbeddedIdIsApplied(): void
    {
        $this->seedUsers();

        $alice = User::with(['addresses' => fn ($query) => $query->orderBy('id', 'desc')])->where('name', 'Alice')->firstOrFail();

        $this->assertSame(['Lyon', 'Paris'], $alice->addresses->pluck('city')->all());
    }

    public function testTheConstraintsSeeTheDocumentsOfTheOpenTransaction(): void
    {
        DB::transaction(function (): void {
            $this->seedUsers();

            $alice = User::with(['addresses' => fn ($query) => $query->where('city', 'Lyon')])->where('name', 'Alice')->firstOrFail();

            $this->assertSame(['Lyon'], $alice->addresses->pluck('city')->all());
        });
    }

    public function testTheCollationOfTheQueryApplies(): void
    {
        $this->seedUsers();

        $users = User::query()
            ->options(['collation' => ['locale' => 'en', 'strength' => 2]])
            ->withWhereHas('addresses', fn ($query) => $query->where('city', 'paris'))
            ->orderBy('name')
            ->get();

        $this->assertSame(['Alice' => ['Paris'], 'Bob' => ['Paris']], self::citiesByName($users));
    }

    /** @param Closure(stdClass): Closure $callback */
    #[DataProvider('provideCallbacksTypedWithTheRelation')]
    public function testACallbackTypedWithTheRelationStillReceivesIt(Closure $callback): void
    {
        $this->seedUsers();
        $received = new stdClass();

        $alice = User::with(['addresses' => $callback($received)])->where('name', 'Alice')->firstOrFail();

        $this->assertInstanceOf(EmbedsMany::class, $received->argument);
        $this->assertSame(['Paris', 'Lyon'], $alice->addresses->pluck('city')->all());
    }

    public static function provideCallbacksTypedWithTheRelation(): iterable
    {
        yield 'named' => [
            static fn (stdClass $received) => static function (EmbedsMany $relation) use ($received): void {
            $received->argument = $relation;
            },
        ];
        yield 'union' => [
            static fn (stdClass $received) => static function (EmbedsMany|EmbedsOne $relation) use ($received): void {
            $received->argument = $relation;
            },
        ];
        yield 'base class' => [
            static fn (stdClass $received) => static function (Relation $relation) use ($received): void {
            $received->argument = $relation;
            },
        ];
    }

    public function testTheEmbeddedDocumentsAreReadAtTheSameTimeAsTheirMatches(): void
    {
        $this->seedUsers();
        $users = User::orderBy('name')->get();
        User::where('name', 'Alice')->firstOrFail()->addresses()->destroy(
            User::where('name', 'Alice')->firstOrFail()->addresses->firstWhere('city', 'Paris'),
        );

        $users->load(['addresses' => fn ($query) => $query->where('city', 'Lyon')]);

        $this->assertSame(['Lyon'], $users[0]->addresses->pluck('city')->all());
    }

    public function testTheParentMayHaveACustomPrimaryKey(): void
    {
        Book::resolveRelationUsing('addresses', fn (Book $book) => $book->embedsMany(Address::class, 'addresses'));
        $book = Book::create(['title' => 'Dune', 'author' => 'Herbert']);
        $book->addresses()->create(['city' => 'Paris']);
        $book->addresses()->create(['city' => 'Lyon']);

        $loaded = Book::with(['addresses' => fn ($query) => $query->where('city', 'Lyon')])->firstOrFail();
        $counted = Book::withCount(['addresses' => fn ($query) => $query->where('city', 'Lyon')])->firstOrFail();
        $narrowed = Book::select('author')->withCount(['addresses' => fn ($query) => $query->where('city', 'Lyon')])->firstOrFail();

        $this->assertSame(['Lyon'], $loaded->getRelation('addresses')->pluck('city')->all());
        $this->assertSame(1, $counted->addresses_count);
        $this->assertSame(1, $narrowed->addresses_count);
    }

    public function testACompoundOrderKeepsItsPrecedence(): void
    {
        $alice = User::create(['name' => 'Alice']);
        $alice->addresses()->create(['city' => 'Paris', 'floor' => 1]);
        $alice->addresses()->create(['city' => 'Lyon', 'floor' => 2]);
        $alice->addresses()->create(['city' => 'Nice', 'floor' => 2]);

        $loaded = User::with(['addresses' => fn ($query) => $query->orderBy('id')->orderBy('floor', 'desc')])->firstOrFail();

        $this->assertSame(['Paris', 'Lyon', 'Nice'], $loaded->addresses->pluck('city')->all());
    }

    public function testExactParentKeysStayApartUnderACaseInsensitiveCollation(): void
    {
        Book::resolveRelationUsing('addresses', fn (Book $book) => $book->embedsMany(Address::class, 'addresses'));
        Book::create(['title' => 'Dune'])->addresses()->create(['city' => 'Paris']);
        Book::create(['title' => 'dune'])->addresses()->create(['city' => 'Lyon']);
        Book::create(['title' => 'Emma'])->addresses()->create(['city' => 'Paris']);

        $books = Book::query()
            ->options(['collation' => ['locale' => 'en', 'strength' => 2]])
            ->with(['addresses' => fn ($query) => $query->where('city', '!=', 'Nice')])
            ->orderBy('title')
            ->get();

        $this->assertSame(
            ['Dune' => ['Paris'], 'dune' => ['Lyon'], 'Emma' => ['Paris']],
            $books->mapWithKeys(fn ($book) => [$book->title => $book->getRelation('addresses')->pluck('city')->all()])->all(),
        );
    }

    public function testABuilderCallbackThatAddsNothingIsEvaluatedOnce(): void
    {
        $this->seedUsers();
        $calls = 0;

        $alice = User::with([
            'addresses' => static function (EloquentBuilder $query) use (&$calls): void {
                ++$calls;
            },
        ])->where('name', 'Alice')->firstOrFail();

        $this->assertSame(1, $calls);
        $this->assertSame(['Paris', 'Lyon'], $alice->addresses->pluck('city')->all());
    }

    public function testConstraintsOnANestedEmbeddedRelationOfAModelWithACustomKeyAreNotSupported(): void
    {
        Book::resolveRelationUsing('addresses', fn (Book $book) => $book->embedsMany(Address::class, 'addresses'));
        User::resolveRelationUsing('embeddedBooks', fn (User $user) => $user->embedsMany(Book::class, 'books', 'user_id', 'embeddedBooks'));
        $user = User::create(['name' => 'Alice']);
        $user->embeddedBooks()->create(['title' => 'Dune']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Constraints on an eager loaded nested embedded relation are not supported');

        User::firstOrFail()->embeddedBooks->load(['addresses' => fn ($query) => $query->where('city', 'Paris')]);
    }

    public function testAFieldNamedLikeTheInternalParentKeyIsKept(): void
    {
        DB::connection('mongodb')->getCollection('users')->insertOne([
            'name' => 'Alice',
            'addresses' => [['city' => 'Paris', '__parent' => 'kept'], ['city' => 'Lyon', '__parent' => 'other']],
        ]);

        $alice = User::with(['addresses' => fn ($query) => $query->orderBy('__parent', 'desc')])->firstOrFail();

        $this->assertSame(['other', 'kept'], $alice->addresses->map(fn ($address) => $address->getAttribute('__parent'))->all());
    }

    public function testTheConstrainedDocumentsKeepTheRepresentationOfAnUnconstrainedRead(): void
    {
        $this->seedUsers();

        $unconstrained = User::with('addresses')->where('name', 'Bob')->firstOrFail()->addresses->first()->toArray();
        $constrained = User::with(['addresses' => fn ($query) => $query->where('city', 'Paris')])->where('name', 'Bob')->firstOrFail()->addresses->first()->toArray();

        $this->assertSame($unconstrained, $constrained);
    }

    public function testAnUnsavedParentLoadsAnEmptyRelation(): void
    {
        $user = (new User())->load(['addresses' => fn ($query) => $query->where('city', 'Paris')]);

        $this->assertCount(0, $user->addresses);
    }

    public function testTheTimeoutOfTheQueryApplies(): void
    {
        $this->seedUsers();
        $subscriber = new class implements CommandSubscriber {
            /** @var list<int|null> */
            public array $timeouts = [];

            public function commandStarted(CommandStartedEvent $event): void
            {
                if ($event->getCommandName() !== 'aggregate') {
                    return;
                }

                $this->timeouts[] = $event->getCommand()->maxTimeMS ?? null;
            }

            public function commandFailed(CommandFailedEvent $event): void
            {
            }

            public function commandSucceeded(CommandSucceededEvent $event): void
            {
            }
        };

        DB::getMongoClient()->getManager()->addSubscriber($subscriber);

        try {
            User::query()->timeout(1)->with(['addresses' => fn ($query) => $query->where('city', 'Paris')])->get();
        } finally {
            DB::getMongoClient()->getManager()->removeSubscriber($subscriber);
        }

        $this->assertSame([1000], $subscriber->timeouts);
    }

    #[TestWith(["\x01aaaaaaaaaaaaaaa"])]
    #[TestWith(['aaaaaaaaaaaaaaaa'])]
    public function testTheParentMayHaveABinaryKey(string $uuid): void
    {
        DB::connection('mongodb')->getCollection('users')->insertOne([
            '_id' => new Binary($uuid, Binary::TYPE_UUID),
            'name' => 'Alice',
            'addresses' => [['city' => 'Paris'], ['city' => 'Lyon']],
        ]);

        $loaded = User::with(['addresses' => fn ($query) => $query->where('city', 'Lyon')])->firstOrFail();
        $counted = User::withCount(['addresses' => fn ($query) => $query->where('city', 'Lyon')])->firstOrFail();

        $this->assertSame(['Lyon'], $loaded->addresses->pluck('city')->all());
        $this->assertSame(1, $counted->addresses_count);
    }

    public function testAConstrainedLoadWorksForABinaryKeyThatCanBeReadAgain(): void
    {
        DB::connection('mongodb')->getCollection('users')->insertOne([
            '_id' => new Binary("\x01aaaaaaaaaaaaaaa", Binary::TYPE_UUID),
            'name' => 'Alice',
            'addresses' => [['city' => 'Paris'], ['city' => 'Lyon']],
        ]);

        $user = User::firstOrFail()->load(['addresses' => fn ($query) => $query->where('city', 'Lyon')]);

        $this->assertSame(['Lyon'], $user->addresses->pluck('city')->all());
    }

    public function testAConstrainedLoadIsRejectedForAKeyThatCannotBeReadAgain(): void
    {
        DB::connection('mongodb')->getCollection('users')->insertOne([
            '_id' => new Binary('aaaaaaaaaaaaaaaa', Binary::TYPE_UUID),
            'name' => 'Alice',
            'addresses' => [['city' => 'Paris'], ['city' => 'Lyon']],
        ]);
        $user = User::firstOrFail();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('could not be read again by its key');

        $user->load(['addresses' => fn ($query) => $query->where('city', 'Lyon')]);
    }

    /** @return iterable<string, array{mixed, mixed}> */
    public static function distinctParentKeys(): iterable
    {
        yield 'integer and string' => [1, '1'];
        yield 'hex string and object id' => ['0123456789abcdef01234567', new ObjectId('0123456789abcdef01234567')];
        yield 'floats beyond the printed precision' => [1.000000000000001, 1.000000000000002];
        yield 'binary subtypes' => [new Binary('aaaaaaaaaaaaaaaa', Binary::TYPE_GENERIC), new Binary('aaaaaaaaaaaaaaaa', Binary::TYPE_UUID)];
    }

    #[DataProvider('distinctParentKeys')]
    public function testParentsWithDistinctKeysStayApart(mixed $first, mixed $second): void
    {
        DB::connection('mongodb')->getCollection('users')->insertMany([
            ['_id' => $first, 'name' => 'First', 'addresses' => [['city' => 'Paris'], ['city' => 'Lyon']]],
            ['_id' => $second, 'name' => 'Second', 'addresses' => [['city' => 'Paris'], ['city' => 'Nice']]],
        ]);

        $users = User::with(['addresses' => fn ($query) => $query->where('city', '!=', 'Paris')])->orderBy('name')->get();
        $counted = User::withCount(['addresses' => fn ($query) => $query->where('city', 'Paris')])->orderBy('name')->get();

        $this->assertSame([['Lyon'], ['Nice']], $users->map(fn ($user) => $user->addresses->pluck('city')->all())->all());
        $this->assertSame([1, 1], $counted->pluck('addresses_count')->all());
    }

    public function testTheParentMayHaveADocumentValuedKey(): void
    {
        DB::connection('mongodb')->getCollection('users')->insertMany([
            ['_id' => ['tenant' => 1, 'sequence' => 2], 'addresses' => [['city' => 'Paris'], ['city' => 'Lyon']]],
            ['_id' => ['tenant' => 1, 'sequence' => 3], 'addresses' => [['city' => 'Nice']]],
        ]);

        $users = User::with(['addresses' => fn ($query) => $query->where('city', 'Paris')])->get();

        $this->assertSame([1, 0], $users->map(fn ($user) => $user->addresses->count())->all());
    }

    public function testTheParentKeyMayBeLeftOutOfTheSelection(): void
    {
        $this->seedUsers();

        $users = User::select('name')->project(['_id' => 0])->withCount(['addresses' => fn ($query) => $query->where('city', 'Paris')])->orderBy('name')->get();

        $this->assertSame(['Alice' => 1, 'Bob' => 1, 'Carol' => 0], $users->pluck('addresses_count', 'name')->all());
    }

    public function testTheDocumentsAreNarrowedAfterThePageOfParentsIsCut(): void
    {
        $this->seedUsers();
        $subscriber = new class implements CommandSubscriber {
            /** @var list<string> */
            public array $stages = [];

            public function commandStarted(CommandStartedEvent $event): void
            {
                if ($event->getCommandName() !== 'aggregate') {
                    return;
                }

                $this->stages = array_map(static fn ($stage) => (string) array_key_first((array) $stage), (array) $event->getCommand()->pipeline);
            }

            public function commandFailed(CommandFailedEvent $event): void
            {
            }

            public function commandSucceeded(CommandSucceededEvent $event): void
            {
            }
        };

        DB::getMongoClient()->getManager()->addSubscriber($subscriber);

        try {
            User::with(['addresses' => fn ($query) => $query->where('city', 'Paris')])->orderBy('name')->limit(2)->get();
        } finally {
            DB::getMongoClient()->getManager()->removeSubscriber($subscriber);
        }

        $this->assertSame(['$sort', '$limit', '$lookup'], $subscriber->stages);
    }

    public function testAnAggregateThatOnlySortsReadsTheDocumentsOfTheParents(): void
    {
        $this->seedUsers();
        $subscriber = new class implements CommandSubscriber {
            public int $aggregations = 0;

            public function commandStarted(CommandStartedEvent $event): void
            {
                $this->aggregations += (int) ($event->getCommandName() === 'aggregate');
            }

            public function commandFailed(CommandFailedEvent $event): void
            {
            }

            public function commandSucceeded(CommandSucceededEvent $event): void
            {
            }
        };

        DB::getMongoClient()->getManager()->addSubscriber($subscriber);

        try {
            $users = User::withCount(['addresses' => fn ($query) => $query->orderBy('city')])->orderBy('name')->get();
        } finally {
            DB::getMongoClient()->getManager()->removeSubscriber($subscriber);
        }

        $this->assertSame([2, 1, 0], $users->pluck('addresses_count')->all());
        $this->assertSame(0, $subscriber->aggregations);
    }

    public function testAnInclusionProjectionKeepsTheNarrowedDocuments(): void
    {
        $this->seedUsers();

        $users = User::project(['name' => 1])->with(['addresses' => fn ($query) => $query->where('city', 'Paris')])->orderBy('name')->get();
        $counted = User::project(['name' => 1])->withCount(['addresses' => fn ($query) => $query->where('city', 'Paris')])->orderBy('name')->get();

        $this->assertSame(['Alice' => 1, 'Bob' => 1, 'Carol' => 0], $users->mapWithKeys(fn ($user) => [$user->name => $user->addresses->count()])->all());
        $this->assertSame(['Alice' => 1, 'Bob' => 1, 'Carol' => 0], $counted->pluck('addresses_count', 'name')->all());
    }

    /** @param array<string, mixed> $projection */
    #[DataProvider('inclusionProjectionsWithoutNamedFields')]
    public function testAnInclusionProjectionWithoutNamedFieldsKeepsTheNarrowedDocuments(array $projection): void
    {
        $this->seedUsers();

        $users = User::project($projection)->with(['addresses' => fn ($query) => $query->where('city', 'Paris')])->orderBy('_id')->get();
        $counted = User::project($projection)->withCount(['addresses' => fn ($query) => $query->where('city', 'Paris')])->orderBy('_id')->get();

        $this->assertSame([1, 1, 0], $users->map(fn ($user) => $user->addresses->count())->all());
        $this->assertSame([1, 1, 0], $counted->pluck('addresses_count')->all());
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function inclusionProjectionsWithoutNamedFields(): array
    {
        return [
            'only the id' => [['_id' => 1]],
            'a computed expression' => [['label' => ['$toUpper' => '$name']]],
            'a computed expression without the id' => [['_id' => 0, 'label' => ['$toUpper' => '$name']]],
        ];
    }

    /**
     * @param list<string>         $columns
     * @param array<string, mixed> $projection
     */
    #[DataProvider('selectionsAndProjections')]
    public function testEverySelectionAndProjectionKeepsTheNarrowedDocuments(array $columns, array $projection): void
    {
        $this->seedUsers();
        $constraints = ['addresses' => fn ($query) => $query->where('city', 'Paris')];

        $users = User::select($columns)->project($projection)->with($constraints)->orderBy('_id')->get();
        $counted = User::select($columns)->project($projection)->withCount($constraints)->orderBy('_id')->get();

        $this->assertSame([1, 1, 0], $users->map(fn ($user) => $user->addresses->count())->all());
        $this->assertSame([1, 1, 0], $counted->pluck('addresses_count')->all());
    }

    /** @return array<string, array{list<string>, array<string, mixed>}> */
    public static function selectionsAndProjections(): array
    {
        return [
            'nothing' => [['*'], []],
            'a selection' => [['name'], []],
            'the id selection' => [['id'], []],
            'an inclusion' => [['*'], ['name' => 1]],
            'an exclusion' => [['*'], ['name' => 0]],
            'an exclusion of the id' => [['*'], ['_id' => 0]],
            'the id only' => [['*'], ['_id' => 1]],
            'a slice' => [['*'], ['addresses' => ['$slice' => 1]]],
            'a computed field' => [['*'], ['label' => ['$toUpper' => '$name']]],
            'a selection and an inclusion' => [['name'], ['age' => 1]],
            'a selection overridden by an exclusion' => [['name'], ['name' => 0]],
            'a selection and an exclusion of the id' => [['name'], ['_id' => 0]],
            'the id selection and an exclusion of the id' => [['id'], ['_id' => 0]],
            'a selection and a slice' => [['name'], ['addresses' => ['$slice' => 1]]],
            'a selection and a computed field' => [['name'], ['label' => ['$toUpper' => '$name']]],
            'the collection wildcard and an exclusion' => [['users.*'], ['name' => 0]],
            'another wildcard and an inclusion' => [['clients.*'], ['name' => 1]],
        ];
    }

    public function testAProjectionOverridesTheSelectionWhenClassifyingTheSelection(): void
    {
        $this->seedUsers();

        $users = User::select('name')->project(['name' => 0])->with(['addresses' => fn ($query) => $query->where('city', 'Paris')])->orderBy('_id')->get();
        $counted = User::select('name')->project(['name' => 0])->withCount(['addresses' => fn ($query) => $query->where('city', 'Paris')])->orderBy('_id')->get();

        $this->assertSame([1, 1, 0], $users->map(fn ($user) => $user->addresses->count())->all());
        $this->assertSame([1, 1, 0], $counted->pluck('addresses_count')->all());
    }

    public function testAConstrainedEmbeddedRelationNestedInAnotherEmbeddedRelationIsRejected(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('nested in another embedded relation');

        User::with(['father' => fn ($query) => $query->where('name', 'Alfred'), 'father.father' => fn ($query) => $query->where('name', 'Mark')])->get();
    }

    public function testAnUnconstrainedEmbeddedRelationNestedInAnotherEmbeddedRelationIsNotRejected(): void
    {
        $alice = User::create(['name' => 'Alice']);
        $father = $alice->father()->create(['name' => 'Alfred']);
        $father->father()->create(['name' => 'Mark']);

        $users = User::with(['father' => fn ($query) => $query->where('name', 'Alfred'), 'father.father'])->get();

        $this->assertSame('Mark', $users[0]->father->father->name);
    }

    public function testAConstrainedReferencedRelationNestedInEmbeddedRelationsIsNotRejected(): void
    {
        User::create(['name' => 'Alice'])->father()->create(['name' => 'Alfred']);

        $users = User::with(['father.father.books' => fn ($query) => $query->where('title', 'Dune')])->get();

        $this->assertSame(['Alice'], $users->pluck('name')->all());
    }

    public function testAConstrainedNestedEmbeddedRelationRegisteredDynamicallyIsRejected(): void
    {
        Book::resolveRelationUsing('addresses', fn (Book $book) => $book->embedsMany(Address::class, 'addresses'));
        User::resolveRelationUsing('embeddedBooks', fn (User $user) => $user->embedsMany(Book::class, 'books', 'user_id', 'embeddedBooks'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('nested in another embedded relation');

        User::with(['embeddedBooks.addresses' => fn ($query) => $query->where('city', 'Paris')])->get();
    }

    /** @param array<string, mixed>|stdClass $projection */
    #[DataProvider('projectionOptions')]
    public function testAProjectionOptionKeepsTheNarrowedDocuments(array|stdClass $projection): void
    {
        $this->seedUsers();
        $constraints = ['addresses' => fn ($query) => $query->where('city', 'Paris')];

        $users = User::query()->options(['projection' => $projection])->with($constraints)->orderBy('_id')->get();
        $counted = User::query()->options(['projection' => $projection])->withCount($constraints)->orderBy('_id')->get();

        $this->assertSame([1, 1, 0], $users->map(fn ($user) => $user->addresses->count())->all());
        $this->assertSame([1, 1, 0], $counted->pluck('addresses_count')->all());
    }

    /** @return array<string, array{array<string, mixed>|stdClass}> */
    public static function projectionOptions(): array
    {
        return [
            'an inclusion' => [['name' => 1]],
            'an inclusion object' => [(object) ['name' => 1]],
            'an exclusion' => [['name' => 0]],
            'an exclusion object' => [(object) ['name' => 0]],
        ];
    }

    public function testAProjectionOptionIsNotModifiedByTheQuery(): void
    {
        $this->seedUsers();
        $projection = (object) ['name' => 1];

        User::query()->options(['projection' => $projection])->with(['addresses' => fn ($query) => $query->where('city', 'Paris')])->get();

        $this->assertEquals((object) ['name' => 1], $projection);
    }

    public function testNestedConstraintsAreRejectedWhenTheParentSelectionOmitsTheEmbeddedAttribute(): void
    {
        $this->seedUsers();

        $user = User::select('name')->with(['addresses' => fn ($query) => $query->where('city', 'Paris')])->where('name', 'Alice')->firstOrFail();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Constraints on an eager loaded nested embedded relation are not supported');

        $user->addresses->load(['addresses' => fn ($query) => $query->where('floor', 1)]);
    }

    public function testAnExclusionProjectionStaysAnExclusionProjection(): void
    {
        $this->seedUsers();

        $users = User::project(['name' => 0])->with(['addresses' => fn ($query) => $query->where('city', 'Paris')])->orderBy('_id')->get();

        $this->assertSame([1, 1, 0], $users->map(fn ($user) => $user->addresses->count())->all());
    }

    public function testAConstrainedEmbeddedAggregateIsRejectedOnAGroupedQuery(): void
    {
        $this->seedUsers();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('grouped query');

        User::select('name', 'addresses')->groupBy('name')->withCount(['addresses' => fn ($query) => $query->where('city', 'Paris')])->get();
    }

    public function testAParentWithAZeroKeyIsReloaded(): void
    {
        DB::connection('mongodb')->getCollection('users')->insertOne(['_id' => 0, 'addresses' => [['city' => 'Paris'], ['city' => 'Lyon']]]);

        $user = User::firstOrFail()->load(['addresses' => fn ($query) => $query->where('city', 'Lyon')]);

        $this->assertSame(['Lyon'], $user->addresses->pluck('city')->all());
    }

    /** @return array<string, list<string>> */
    private static function citiesByName(Collection $users): array
    {
        return $users->mapWithKeys(fn ($user) => [$user->name => $user->addresses->pluck('city')->all()])->all();
    }

    private function seedUsers(): void
    {
        $alice = User::create(['name' => 'Alice']);
        $alice->addresses()->create(['city' => 'Paris', 'floor' => 1]);
        $alice->addresses()->create(['city' => 'Lyon', 'floor' => 2]);

        $bob = User::create(['name' => 'Bob']);
        $bob->addresses()->create(['city' => 'Paris', 'floor' => 5]);

        User::create(['name' => 'Carol']);
    }
}
