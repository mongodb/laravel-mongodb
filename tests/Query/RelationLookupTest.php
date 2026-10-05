<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Query;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use LogicException;
use MongoDB\Laravel\Query\RelationLookup;
use MongoDB\Laravel\Tests\Models\Book;
use MongoDB\Laravel\Tests\Models\Client;
use MongoDB\Laravel\Tests\Models\Label;
use MongoDB\Laravel\Tests\Models\Photo;
use MongoDB\Laravel\Tests\Models\User;
use MongoDB\Laravel\Tests\TestCase;

use function array_slice;

class RelationLookupTest extends TestCase
{
    public function testBelongsToMatchesTheOwnerKeyAgainstTheForeignKey()
    {
        $stage = $this->lookupFor(fn () => (new Book())->author());

        $this->assertSame('users', $stage['from']);
        $this->assertSame(['local' => '$author_id'], $stage['let']);
        $this->assertSame('__rel', $stage['as']);
        $this->assertSame([
            ['$match' => ['$expr' => ['$eq' => [['$toString' => '$_id'], ['$toString' => '$$local']]]]],
        ], $stage['pipeline']);
    }

    public function testHasOneMatchesTheForeignKeyAgainstTheLocalKey()
    {
        $stage = $this->lookupFor(fn () => (new User())->role());

        $this->assertSame('roles', $stage['from']);
        $this->assertSame(['local' => '$_id'], $stage['let']);
        $this->assertSame([
            ['$match' => ['$expr' => ['$eq' => [['$toString' => '$user_id'], ['$toString' => '$$local']]]]],
        ], $stage['pipeline']);
    }

    public function testHasManyMatchesTheForeignKeyAgainstTheLocalKey()
    {
        $stage = $this->lookupFor(fn () => (new User())->books());

        $this->assertSame('books', $stage['from']);
        $this->assertSame(['local' => '$_id'], $stage['let']);
        $this->assertSame([
            ['$match' => ['$expr' => ['$eq' => [['$toString' => '$author_id'], ['$toString' => '$$local']]]]],
        ], $stage['pipeline']);
    }

    public function testBelongsToManyMatchesTheRelatedKeyAgainstThePivotKeyArray()
    {
        $stage = $this->lookupFor(fn () => (new User())->clients());

        $this->assertSame('clients', $stage['from']);
        $this->assertSame(['local' => '$client_ids'], $stage['let']);
        $this->assertSame([
            [
                '$match' => [
                    '$expr' => [
                        '$in' => [
                            ['$toString' => '$_id'],
                            ['$map' => ['input' => ['$ifNull' => ['$$local', []]], 'in' => ['$toString' => '$$this']]],
                        ],
                    ],
                ],
            ],
        ], $stage['pipeline']);
    }

    public function testMorphManyAlsoMatchesTheMorphType()
    {
        $stage = $this->lookupFor(fn () => (new User())->photos());

        $this->assertSame('photos', $stage['from']);
        $this->assertSame(['local' => '$_id'], $stage['let']);
        $this->assertSame(
            ['$match' => ['$expr' => ['$eq' => [['$toString' => '$has_image_id'], ['$toString' => '$$local']]]]],
            $stage['pipeline'][0],
        );
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
        $stage = $this->lookupFor(fn () => (new User())->labels());

        $this->assertSame('labels', $stage['from']);
        $this->assertSame(['local' => '$label_ids'], $stage['let']);
        $this->assertSame(
            ['$match' => ['labelleds.labelled_type' => User::class]],
            $stage['pipeline'][1],
        );
    }

    public function testMorphedByManyFiltersTheParentPivotArrayByMorphType()
    {
        $stage = $this->lookupFor(fn () => (new Label())->users());

        $this->assertSame('users', $stage['from']);
        $this->assertSame(['local' => '$labelleds'], $stage['let']);
        $this->assertSame([
            [
                '$match' => [
                    '$expr' => [
                        '$in' => [
                            ['$toString' => '$_id'],
                            [
                                '$map' => [
                                    'input' => [
                                        '$filter' => [
                                            'input' => ['$ifNull' => ['$$local', []]],
                                            'cond' => ['$eq' => ['$$this.labelled_type', User::class]],
                                        ],
                                    ],
                                    'in' => ['$toString' => '$$this.labelled_id'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $stage['pipeline']);
    }

    public function testSourceAliasChainsTheLocalValueThroughThePreviousLookup()
    {
        $stage = $this->lookupFor(fn () => (new User())->books(), '__rel_author_books', '__rel_author');

        $this->assertSame(['local' => ['$first' => '$__rel_author._id']], $stage['let']);
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

    public function testEmbedsManyIsNotSupported()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('EmbedsMany is not supported for relationship lookups. The related documents are already part of the parent document. Read them from the attribute, or match them on their dotted path.');

        $this->lookupFor(fn () => (new User())->addresses());
    }

    public function testEmbedsOneIsNotSupported()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('EmbedsOne is not supported for relationship lookups. The related documents are already part of the parent document. Read them from the attribute, or match them on their dotted path.');

        $this->lookupFor(fn () => (new User())->father());
    }

    public function testFieldMapsIdToUnderscoreId()
    {
        $this->assertSame('_id', RelationLookup::field('id'));
    }

    public function testFieldLeavesOtherNamesUnchanged()
    {
        $this->assertSame('author_id', RelationLookup::field('author_id'));
        $this->assertSame('_id', RelationLookup::field('_id'));
        $this->assertSame('data.client_id', RelationLookup::field('data.client_id'));
        $this->assertSame('identifier', RelationLookup::field('identifier'));
    }

    public function testAliasIsExposedAlongsideTheStage()
    {
        $lookup = RelationLookup::for(
            Relation::noConstraints(fn () => (new Book())->author()),
            '__rel_author',
            null,
        );

        $this->assertSame('__rel_author', $lookup->alias);
        $this->assertArrayHasKey('$lookup', $lookup->stage);
    }

    /** @return array<string, mixed> */
    private function lookupFor(
        callable $relation,
        string $alias = '__rel',
        ?string $sourceAlias = null,
        ?callable $constrainRelated = null,
    ): array {
        $lookup = RelationLookup::for(
            Relation::noConstraints($relation),
            $alias,
            $sourceAlias,
            $constrainRelated === null ? null : $constrainRelated(...),
        );

        return $lookup->stage['$lookup'];
    }
}
