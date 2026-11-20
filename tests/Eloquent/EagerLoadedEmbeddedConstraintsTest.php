<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Eloquent;

use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use LogicException;
use MongoDB\BSON\Binary;
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

class EagerLoadedEmbeddedConstraintsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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

        $this->assertSame([['Paris'], ['Paris'], []], $users->map(fn ($user) => $user->addresses->pluck('city')->all())->all());
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

    public function testALimitOnTheEagerLoadedConstraintsIsNotSupported(): void
    {
        $this->seedUsers();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A "limit()" on the eager loaded constraints of the embedded relation EmbedsMany is not supported');

        User::with(['addresses' => fn ($query) => $query->limit(1)])->get();
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

        $this->assertSame([['Lyon'], [], []], $users->map(fn ($user) => $user->addresses->pluck('city')->all())->all());
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

        $this->assertSame([['Paris'], ['Paris']], $users->map(fn ($user) => $user->addresses->pluck('city')->all())->all());
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
