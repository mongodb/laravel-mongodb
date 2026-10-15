<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Helpers;

use Closure;
use Generator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use LogicException;
use MongoDB\BSON\ObjectId;
use MongoDB\Laravel\Helpers\RelationLookup;
use MongoDB\Laravel\Tests\Models\Book;
use MongoDB\Laravel\Tests\Models\Client;
use MongoDB\Laravel\Tests\Models\HiddenAnimal;
use MongoDB\Laravel\Tests\Models\Label;
use MongoDB\Laravel\Tests\Models\Photo;
use MongoDB\Laravel\Tests\Models\SqlRole;
use MongoDB\Laravel\Tests\Models\User;
use MongoDB\Laravel\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;

use function array_map;
use function array_slice;
use function count;
use function sprintf;
use function strtoupper;

class RelationLookupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->skipIfRelationLookupIsNotSupported();
    }

    protected function tearDown(): void
    {
        User::truncate();
        Book::truncate();
        Client::truncate();

        parent::tearDown();
    }

    public function testBelongsToMatchesTheOwnerKeyAgainstTheForeignKey()
    {
        $relation = fn () => (new Book())->author();
        $stage = $this->lookupFor($relation);

        $this->assertSame('users', $stage['from']);
        $this->assertSame('__rel_keys', $stage['localField']);
        $this->assertSame('_id', $stage['foreignField']);
        $this->assertSame('__rel', $stage['as']);
        $this->assertSame([['$match' => ['$expr' => ['$gt' => [['$size' => '$$laravel_mongodb_relation_keys'], 0]]]]], $stage['pipeline']);
    }

    public function testHasOneMatchesTheForeignKeyAgainstTheLocalKey()
    {
        $relation = fn () => (new User())->role();
        $stage = $this->lookupFor($relation);

        $this->assertSame('roles', $stage['from']);
        $this->assertSame('user_id', $stage['foreignField']);
        $this->assertSame([['$match' => ['$expr' => ['$gt' => [['$size' => '$$laravel_mongodb_relation_keys'], 0]]]]], $stage['pipeline']);
    }

    public function testHasManyMatchesTheForeignKeyAgainstTheLocalKey()
    {
        $relation = fn () => (new User())->books();
        $stage = $this->lookupFor($relation);

        $this->assertSame('books', $stage['from']);
        $this->assertSame('author_id', $stage['foreignField']);
        $this->assertSame([['$match' => ['$expr' => ['$gt' => [['$size' => '$$laravel_mongodb_relation_keys'], 0]]]]], $stage['pipeline']);
    }

    public function testBelongsToManyMatchesTheForeignPivotKeyAgainstTheParentKey()
    {
        $relation = fn () => (new User())->clients();
        $stage = $this->lookupFor($relation);

        $this->assertSame('clients', $stage['from']);
        $this->assertSame('user_ids', $stage['foreignField']);
        $this->assertSame([['$match' => ['$expr' => ['$gt' => [['$size' => '$$laravel_mongodb_relation_keys'], 0]]]]], $stage['pipeline']);
    }

    public function testMorphManyAlsoMatchesTheMorphType()
    {
        $relation = fn () => (new User())->photos();
        $stage = $this->lookupFor($relation);

        $this->assertSame('photos', $stage['from']);
        $this->assertSame('has_image_id', $stage['foreignField']);
        $this->assertSame(
            ['$match' => ['has_image_type' => User::class]],
            $stage['pipeline'][1],
        );
    }

    public function testMorphOneAlsoMatchesTheMorphType()
    {
        $stage = $this->lookupFor(fn () => (new Client())->photo());

        $this->assertSame('photos', $stage['from']);
        $this->assertSame(
            ['$match' => ['has_image_type' => Client::class]],
            $stage['pipeline'][1],
        );
    }

    public function testMorphToManyMatchesTheMorphTypeInThePivotArrayOfTheRelated()
    {
        $relation = fn () => (new User())->labels();
        $stage = $this->lookupFor($relation);

        $this->assertSame('labels', $stage['from']);
        $this->assertSame(
            ['$match' => ['labelleds.labelled_type' => User::class]],
            $stage['pipeline'][1],
        );
    }

    public function testMorphedByManyFiltersTheParentPivotArrayByMorphType()
    {
        $relation = fn () => (new Label())->users();
        $stage = $this->lookupFor($relation);

        $this->assertSame('users', $stage['from']);
        $this->assertSame('_id', $stage['foreignField']);
        $this->assertSame([
            '$filter' => [
                'input' => ['$ifNull' => ['$labelleds', []]],
                'cond' => ['$eq' => ['$$this.labelled_type', User::class]],
            ],
        ], $this->morphedByManyKeys());
        $this->assertSame([['$match' => ['$expr' => ['$gt' => [['$size' => '$$laravel_mongodb_relation_keys'], 0]]]]], $stage['pipeline']);
    }

    public function testTheAliasNamesTheFieldTheRelatedDocumentsLandIn()
    {
        $stage = $this->lookupFor(fn () => (new User())->books(), '__rel_author_books');

        $this->assertSame('__rel_author_books', $stage['as']);
    }

    public function testConstraintClosureCompilesIntoAnExtraMatchStage()
    {
        $stage = $this->lookupFor(
            fn () => (new User())->books(),
            constrainRelated: fn (EloquentBuilder $query) => $query->where('title', 'Refactoring')->where('year', '>', 1999),
        );

        $expected = (new Book())->newQuery()
            ->where('title', 'Refactoring')
            ->where('year', '>', 1999)
            ->getQuery()
            ->toMql()['find'][0];

        $this->assertCount(2, $stage['pipeline']);
        $this->assertSame(['$match' => $expected], $stage['pipeline'][1]);
    }

    public function testConstraintClosureOrWhereStaysWithinItsOwnGroup()
    {
        $stage = $this->lookupFor(
            fn () => (new User())->books()->where('year', 2026),
            constrainRelated: fn (EloquentBuilder $query) => $query->where('title', 'A')->orWhere('title', 'B'),
        );

        $this->assertEquals(
            ['$match' => ['$and' => [['year' => 2026], ['$or' => [['title' => 'A'], ['title' => 'B']]]]]],
            $stage['pipeline'][1],
        );
    }

    public function testConstraintClosureIsAppendedAfterTheMorphTypeMatch()
    {
        $stage = $this->lookupFor(
            fn () => (new User())->photos(),
            constrainRelated: fn (EloquentBuilder $query) => $query->where('name', 'avatar'),
        );

        $this->assertSame(
            [
                ['$match' => ['has_image_type' => User::class]],
                ['$match' => ['name' => 'avatar']],
            ],
            array_slice($stage['pipeline'], 1),
        );
    }

    public function testAnEmptyConstraintClosureAddsNoStage()
    {
        $stage = $this->lookupFor(fn () => (new User())->books(), constrainRelated: static function (): void {
        });

        $this->assertCount(1, $stage['pipeline']);
    }

    public function testGlobalScopesOfTheRelatedModelApplyWithoutAConstraintClosure()
    {
        $stage = $this->lookupFor(fn () => (new User())->softs());

        $this->assertSame(
            ['$match' => ['deleted_at' => null]],
            $stage['pipeline'][1],
        );
    }

    public function testGlobalScopesOfTheRelatedModelApplyAlongsideAConstraintClosure()
    {
        $stage = $this->lookupFor(
            fn () => (new User())->softs(),
            constrainRelated: fn (EloquentBuilder $query) => $query->where('title', 'The Witcher'),
        );

        $this->assertSame(
            ['$match' => ['$and' => [['title' => 'The Witcher'], ['deleted_at' => null]]]],
            $stage['pipeline'][1],
        );
    }

    public function testConstraintClosureKeepsSortSkipLimitAndProjection()
    {
        $stage = $this->lookupFor(
            fn () => (new User())->books(),
            constrainRelated: fn (EloquentBuilder $query) => $query
                ->where('year', '>', 1999)
                ->orderBy('title')
                ->offset(2)
                ->limit(5)
                ->select('title'),
        );

        $this->assertSame([
            ['$match' => ['year' => ['$gt' => 1999]]],
            ['$sort' => ['title' => 1]],
            ['$skip' => 2],
            ['$limit' => 5],
            ['$project' => ['title' => true]],
        ], array_slice($stage['pipeline'], 1));
    }

    public function testConstraintClosureProducingAnAggregationIsNotSupported()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Relationship constraints producing a "aggregate" command are not supported for relationship lookups.');

        $this->lookupFor(
            fn () => (new User())->books(),
            constrainRelated: fn (EloquentBuilder $query) => $query->groupBy('title'),
        );
    }

    public function testMorphToIsNotSupported()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('MorphTo is not supported for relationship lookups. The related collection differs per document, which one lookup cannot express. Eager load the relation with "with()", or query each morph type on its own.');

        $this->lookupFor(fn () => (new Photo())->hasImage());
    }

    public function testARelationToAnSqlModelIsNotSupported()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf(
            'The hybrid relation to [%s] is not supported for relationship lookups. A lookup reads a collection of the database it runs in, so the related model must be stored in MongoDB on connection [mongodb], not on connection [sqlite].',
            SqlRole::class,
        ));

        $this->lookupFor(fn () => (new User())->sqlRole());
    }

    public function testKeyFieldsAreResolvedThroughTheGrammar()
    {
        $stage = $this->lookupFor(fn () => (new User())->hasMany(Book::class, 'data.id'));

        $this->assertSame('data._id', $stage['foreignField']);
    }

    public function testARelatedModelOnTheDefaultConnectionIsAccepted()
    {
        $stage = $this->lookupFor(fn () => (new User())->hasMany(HiddenAnimal::class, 'user_id'));

        $this->assertSame('hidden_animals', $stage['from']);
    }

    #[DataProvider('provideUnsupportedFilterOperators')]
    public function testAFilterOperatorIsNotSupported(string $expectedMessage, callable $constraint)
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->lookupFor(fn () => (new User())->books(), constrainRelated: $constraint);
    }

    public static function provideUnsupportedFilterOperators(): iterable
    {
        yield '$text' => [
            'A "$text" constraint is not supported for relationship lookups.',
            static fn (EloquentBuilder $query) => $query->where('$text', ['$search' => 'operating']),
        ];

        yield '$near' => [
            'A "$near" constraint is not supported for relationship lookups.',
            static fn (EloquentBuilder $query) => $query->where('location', 'near', [1, 2]),
        ];

        yield '$nearSphere' => [
            'A "$nearSphere" constraint is not supported for relationship lookups.',
            static fn (EloquentBuilder $query) => $query->where('location', 'nearSphere', [1, 2]),
        ];
    }

    #[DataProvider('provideEmbeddedRelations')]
    public function testAnEmbeddedRelationIsNotSupported(string $expectedMessage, callable $relation)
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->lookupFor($relation);
    }

    public static function provideEmbeddedRelations(): iterable
    {
        yield 'EmbedsMany' => [
            'EmbedsMany is not supported for relationship lookups. The related documents are already part of the parent document. Read them from the attribute, or match them on their dotted path.',
            static fn () => (new User())->addresses(),
        ];

        yield 'EmbedsOne' => [
            'EmbedsOne is not supported for relationship lookups. The related documents are already part of the parent document. Read them from the attribute, or match them on their dotted path.',
            static fn () => (new User())->father(),
        ];
    }

    #[DataProvider('provideRemovedGlobalScopes')]
    public function testAGlobalScopeCanBeRemoved(callable $relation, ?callable $constraint)
    {
        $stage = $this->lookupFor($relation, constrainRelated: $constraint);

        $this->assertCount(1, $stage['pipeline']);
    }

    public static function provideRemovedGlobalScopes(): iterable
    {
        yield 'relation' => [static fn () => (new User())->softsWithTrashed(), null];

        yield 'constraint closure' => [
            static fn () => (new User())->softs(),
            static fn (EloquentBuilder $query) => $query->withTrashed(),
        ];
    }

    public function testConstraintsDeclaredOnTheRelationAreKept()
    {
        $stage = $this->lookupFor(fn () => (new User())->books()->where('title', 'Refactoring'));

        $this->assertSame(['$match' => ['title' => 'Refactoring']], $stage['pipeline'][1]);
    }

    public function testTheCollectionNameIncludesTheConnectionPrefix()
    {
        $connection = DB::connection('mongodb');
        $connection->setTablePrefix('prefix_');

        try {
            $stage = $this->lookupFor(fn () => (new User())->books());

            $this->assertSame('prefix_books', $stage['from']);
        } finally {
            $connection->setTablePrefix('');
        }
    }

    #[DataProvider('relationsWithStoredKeys')]
    public function testKeysMatchAsTheRelationQueryMatchesThem(string $parentCollection, string $relatedCollection, Closure $relation, Closure $documents)
    {
        $connection = DB::connection('mongodb');
        $types = ['objectId', 'hex', 'uppercase hex', 'integer', 'numeric string'];
        $pair = 0;

        foreach ($types as $parentType) {
            foreach ($types as $relatedType) {
                $pair++;
                [$parent, $related] = $documents(
                    self::storedKey($parentType, $pair),
                    self::storedKey($relatedType, $pair),
                    $parentType . ' to ' . $relatedType,
                );
                $connection->getCollection($parentCollection)->insertOne($parent);
                $connection->getCollection($relatedCollection)->insertOne($related);
            }
        }

        $lookup = RelationLookup::for(Relation::noConstraints(fn () => $relation(null)), '__rel');
        $matchedByLookup = [];
        foreach ($connection->getCollection($parentCollection)->aggregate($lookup->stages) as $document) {
            $matchedByLookup[$document->pair] = count((array) $document->__rel);
        }

        $matchedByRelation = [];
        foreach ($relation(null)->getParent()->newQuery()->get() as $model) {
            $matchedByRelation[$model->pair] = $relation($model)->count();
        }

        $this->assertEquals($matchedByRelation, $matchedByLookup);
    }

    public static function relationsWithStoredKeys(): Generator
    {
        yield 'belongs to an id' => [
            'books',
            'users',
            fn (?Book $book) => ($book ?? new Book())->author(),
            fn ($parentKey, $relatedKey, $pair) => [['pair' => $pair, 'author_id' => $parentKey], ['_id' => $relatedKey]],
        ];

        yield 'belongs to an embedded id' => [
            'books',
            'users',
            fn (?Book $book) => ($book ?? new Book())->belongsTo(User::class, 'owner_id', 'profile._id'),
            fn ($parentKey, $relatedKey, $pair) => [['pair' => $pair, 'owner_id' => $parentKey], ['profile' => ['_id' => $relatedKey]]],
        ];

        yield 'belongs to another key' => [
            'books',
            'users',
            fn (?Book $book) => ($book ?? new Book())->belongsTo(User::class, 'author_code', 'code'),
            fn ($parentKey, $relatedKey, $pair) => [['pair' => $pair, 'author_code' => $parentKey], ['code' => $relatedKey]],
        ];

        yield 'has many' => [
            'users',
            'books',
            fn (?User $user) => ($user ?? new User())->books(),
            fn ($parentKey, $relatedKey, $pair) => [['_id' => $parentKey, 'pair' => $pair], ['author_id' => $relatedKey]],
        ];

        yield 'belongs to many' => [
            'users',
            'clients',
            fn (?User $user) => ($user ?? new User())->clients(),
            fn ($parentKey, $relatedKey, $pair) => [
                ['_id' => $parentKey, 'pair' => $pair, 'client_ids' => [$relatedKey]],
                ['_id' => $relatedKey, 'user_ids' => [$parentKey]],
            ],
        ];
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testBelongsToManyMatchesTheParentKeyInTheForeignPivotArrayOfTheRelated(bool $withNullKey)
    {
        $user = User::create(['name' => 'Without client ids']);
        Client::create(['name' => 'Listing the user', 'user_ids' => $withNullKey ? [$user->id, null] : [$user->id]]);

        $lookup = RelationLookup::for(Relation::noConstraints(fn () => (new User())->clients()), '__rel');
        $clients = DB::connection('mongodb')->getCollection('users')->aggregate($lookup->stages)->toArray()[0]->__rel;

        $this->assertSame(1, $user->clients()->count());
        $this->assertCount(1, $clients);
    }

    public function testTheCollectionOfTheRelationQueryIsLookedUp()
    {
        $stage = $this->lookupFor(fn () => (new User())->books()->from('archived_books'));

        $this->assertSame('archived_books', $stage['from']);
    }

    public function testTheMorphTypeFieldIsResolvedThroughTheGrammar()
    {
        $stage = $this->lookupFor(fn () => (new User())->morphMany(Photo::class, 'has_image', 'meta->type', 'has_image_id'));

        $this->assertSame(['$match' => ['meta.type' => User::class]], $stage['pipeline'][1]);
    }

    public function testAParentWithoutAKeyMatchesNothing()
    {
        $authors = $this->authorsByTitle(
            fn () => (new Book())->belongsTo(User::class, 'author_code', 'code'),
            [['name' => 'Without code']],
            [['title' => 'Without author code']],
        );

        $this->assertEquals(['Without author code' => []], $authors);
    }

    #[TestWith(['collation', ['locale' => 'en', 'strength' => 2]])]
    #[TestWith(['min', ['year' => 2000]])]
    #[TestWith(['max', ['year' => 2010]])]
    #[TestWith(['returnKey', true])]
    #[TestWith(['let', ['year' => 2000]])]
    public function testAnOptionTheLookupCannotApplyIsNotSupported(string $option, mixed $value)
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf('A "%s" option on a relationship constraint is not supported for relationship lookups.', $option));

        $this->lookupFor(
            fn () => (new User())->books(),
            constrainRelated: function (EloquentBuilder $query) use ($option, $value): void {
                $query->getQuery()->options([$option => $value]);
            },
        );
    }

    /**
     * @param list<array<string, mixed>> $users
     * @param list<array<string, mixed>> $books
     *
     * @return array<string, list<string>>
     */
    private function authorsByTitle(callable $relation, array $users, array $books): array
    {
        $connection = DB::connection('mongodb');

        try {
            $connection->getCollection('users')->drop();
            $connection->getCollection('books')->drop();
            $connection->getCollection('users')->insertMany($users);
            $connection->getCollection('books')->insertMany($books);

            $lookup = RelationLookup::for(Relation::noConstraints($relation), 'author');

            $authors = [];
            foreach ($connection->getCollection('books')->aggregate($lookup->stages) as $document) {
                $authors[$document->title] = array_map(static fn ($user) => $user->name, (array) $document->author);
            }

            return $authors;
        } finally {
            $connection->getCollection('users')->drop();
            $connection->getCollection('books')->drop();
        }
    }

    private static function storedKey(string $type, int $pair): ObjectId|int|string
    {
        $objectId = new ObjectId(sprintf('%024x', $pair));

        return match ($type) {
            'objectId' => $objectId,
            'hex' => (string) $objectId,
            'uppercase hex' => strtoupper((string) $objectId),
            'integer' => $pair,
            'numeric string' => (string) $pair,
        };
    }

    /** @return array<string, mixed> */
    private function morphedByManyKeys(): array
    {
        $lookup = RelationLookup::for(Relation::noConstraints(fn () => (new Label())->users()), '__rel');

        return $lookup->stages[0]['$addFields']['__rel_keys']['$filter']['input']['$map']['input'];
    }

    /** @return array<string, mixed> */
    private function lookupFor(
        callable $relation,
        string $alias = '__rel',
        ?callable $constrainRelated = null,
    ): array {
        $lookup = RelationLookup::for(
            Relation::noConstraints($relation),
            $alias,
            $constrainRelated === null ? null : $constrainRelated(...),
        );

        return $lookup->stages[1]['$lookup'];
    }
}
