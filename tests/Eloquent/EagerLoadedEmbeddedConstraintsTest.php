<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Eloquent;

use Illuminate\Support\Facades\DB;
use LogicException;
use MongoDB\Laravel\Tests\Models\User;
use MongoDB\Laravel\Tests\TestCase;

class EagerLoadedEmbeddedConstraintsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        User::truncate();
    }

    protected function tearDown(): void
    {
        User::truncate();

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
