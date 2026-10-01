<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Ticket;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Eloquent\HybridRelations;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\MorphMany as MongoMorphMany;
use MongoDB\Laravel\Relations\MorphOne as MongoMorphOne;
use MongoDB\Laravel\Tests\TestCase;
use PDOException;

use function json_encode;

/**
 * Polymorphic one-to-one and one-to-many relations from a SQL model to a MongoDB model
 * must query the bare "{name}_id" and "{name}_type" fields. The related field names must
 * never be qualified with the collection name ("meta.metable_id"), which is what Laravel
 * does for SQL tables.
 *
 * @see https://github.com/mongodb/laravel-mongodb/issues/2875
 */
class GH2875Test extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        try {
            DB::connection('sqlite')->select('SELECT 1');
        } catch (PDOException) {
            $this->markTestSkipped('SQLite connection is not available.');
        }

        GH2875SqlProduct::executeSchema();
        GH2875Meta::truncate();
    }

    public function tearDown(): void
    {
        GH2875SqlProduct::truncate();
        GH2875Meta::truncate();

        parent::tearDown();
    }

    public function testMorphOneUsesMongoRelationClassAndUnqualifiedKeys(): void
    {
        $product = GH2875SqlProduct::create(['name' => 'Laptop']);

        $relation = $product->meta();
        $this->assertInstanceOf(MongoMorphOne::class, $relation);
        $this->assertSame('metable_id', $relation->getForeignKeyName());
        $this->assertSame('metable_id', $relation->getQualifiedForeignKeyName());
        $this->assertSame('metable_type', $relation->getMorphType());
        $this->assertSame('metable_type', $relation->getQualifiedMorphType());

        $filter = $relation->toMql()['find'][0];
        $this->assertSame([
            '$and' => [
                ['metable_type' => GH2875SqlProduct::class],
                ['metable_id' => $product->id],
                ['metable_id' => ['$ne' => null]],
            ],
        ], $filter);
        $this->assertStringNotContainsString('meta.', json_encode($filter));

        $relation = $product->metas();
        $this->assertInstanceOf(MongoMorphMany::class, $relation);
        $this->assertSame('metable_id', $relation->getForeignKeyName());
        $this->assertSame('metable_type', $relation->getMorphType());
        $this->assertStringNotContainsString('meta.', json_encode($relation->toMql()['find'][0]));
    }

    public function testMorphOneMakeAndCreateSetUnqualifiedAttributes(): void
    {
        $product = GH2875SqlProduct::create(['name' => 'Laptop']);

        $meta = $product->meta()->make(['color' => 'silver']);
        $this->assertSame([
            'color' => 'silver',
            'metable_id' => $product->id,
            'metable_type' => GH2875SqlProduct::class,
        ], $meta->getAttributes());

        $product->meta()->create(['color' => 'silver']);

        $document = GH2875Meta::raw()->findOne(['color' => 'silver']);
        $this->assertSame($product->id, $document['metable_id']);
        $this->assertSame(GH2875SqlProduct::class, $document['metable_type']);
        $this->assertArrayNotHasKey('meta', (array) $document);
    }

    public function testMorphOneAndMorphManyLazyAndEagerLoading(): void
    {
        $laptop = GH2875SqlProduct::create(['name' => 'Laptop']);
        $phone = GH2875SqlProduct::create(['name' => 'Phone']);
        $cable = GH2875SqlProduct::create(['name' => 'Cable']);

        $laptop->meta()->create(['color' => 'silver']);
        $phone->metas()->createMany([['color' => 'black'], ['color' => 'white']]);

        // Lazy loading
        $this->assertSame('silver', $laptop->meta->color);
        $this->assertCount(2, $phone->metas);
        $this->assertNull($cable->meta);
        $this->assertCount(0, $cable->metas);

        // Eager loading of several parents uses whereIn on the integer SQL key
        $products = GH2875SqlProduct::with(['meta', 'metas'])->orderBy('id')->get();
        $this->assertCount(3, $products);

        $this->assertTrue($products[0]->relationLoaded('meta'));
        $this->assertSame('silver', $products[0]->meta->color);
        $this->assertCount(1, $products[0]->metas);

        $this->assertInstanceOf(GH2875Meta::class, $products[1]->meta);
        $this->assertSame(['black', 'white'], $products[1]->metas->pluck('color')->sort()->values()->all());

        $this->assertNull($products[2]->meta);
        $this->assertCount(0, $products[2]->metas);
    }

    public function testHasAndWhereHasOnHybridMorphRelations(): void
    {
        $laptop = GH2875SqlProduct::create(['name' => 'Laptop']);
        $phone = GH2875SqlProduct::create(['name' => 'Phone']);
        GH2875SqlProduct::create(['name' => 'Cable']);

        $laptop->meta()->create(['color' => 'silver']);
        $phone->metas()->createMany([['color' => 'black'], ['color' => 'white']]);

        $this->assertSame(['Laptop', 'Phone'], GH2875SqlProduct::has('meta')->orderBy('id')->pluck('name')->all());
        $this->assertSame(['Phone'], GH2875SqlProduct::has('metas', '>=', 2)->pluck('name')->all());
        $this->assertSame(['Cable'], GH2875SqlProduct::doesntHave('meta')->pluck('name')->all());

        $names = GH2875SqlProduct::whereHas('metas', fn ($query) => $query->where('color', 'white'))
            ->pluck('name')
            ->all();
        $this->assertSame(['Phone'], $names);
    }

    public function testInverseMorphToFromMongoToSql(): void
    {
        $laptop = GH2875SqlProduct::create(['name' => 'Laptop']);
        $phone = GH2875SqlProduct::create(['name' => 'Phone']);

        $laptop->meta()->create(['color' => 'silver']);
        $phone->meta()->create(['color' => 'black']);

        // Lazy loading
        $meta = GH2875Meta::where('color', 'black')->first();
        $this->assertInstanceOf(GH2875SqlProduct::class, $meta->metable);
        $this->assertSame($phone->id, $meta->metable->id);

        // Eager loading
        $metas = GH2875Meta::with('metable')->orderBy('color')->get();
        $this->assertCount(2, $metas);
        $this->assertTrue($metas[0]->relationLoaded('metable'));
        $this->assertSame('Phone', $metas[0]->metable->name);
        $this->assertSame('Laptop', $metas[1]->metable->name);
    }
}

class GH2875Meta extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'meta';
    protected static $unguarded = true;

    public function metable(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'metable_type', 'metable_id');
    }
}

class GH2875SqlProduct extends EloquentModel
{
    use HybridRelations;

    protected $connection = 'sqlite';
    protected $table = 'gh2875_products';
    protected static $unguarded = true;

    public function meta(): MorphOne
    {
        return $this->morphOne(GH2875Meta::class, 'metable');
    }

    public function metas(): MorphMany
    {
        return $this->morphMany(GH2875Meta::class, 'metable');
    }

    public static function executeSchema(): void
    {
        $schema = Schema::connection('sqlite');

        $schema->dropIfExists('gh2875_products');
        $schema->create('gh2875_products', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->timestamps();
        });
    }
}
