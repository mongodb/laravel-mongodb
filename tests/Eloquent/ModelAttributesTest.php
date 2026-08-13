<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Eloquent;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Facades\DB;
use MongoDB\Laravel\Connection as MongoDBConnection;
use MongoDB\Laravel\Tests\Models\AttributedAuthor;
use MongoDB\Laravel\Tests\Models\AttributedDeclaredKeyTypeDocument;
use MongoDB\Laravel\Tests\Models\AttributedDocument;
use MongoDB\Laravel\Tests\Models\AttributedDocumentFactory;
use MongoDB\Laravel\Tests\Models\AttributedGuardedDocument;
use MongoDB\Laravel\Tests\Models\AttributedIntegerKeyDocument;
use MongoDB\Laravel\Tests\Models\AttributedTraitDocument;
use MongoDB\Laravel\Tests\Models\AttributedUnguardedDocument;
use MongoDB\Laravel\Tests\Models\AttributedVisibleDocument;
use MongoDB\Laravel\Tests\TestCase;

use function get_debug_type;
use function method_exists;
use function sleep;

final class ModelAttributesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! self::supportsModelAttributes()) {
            $this->markTestSkipped('Configuring models with attributes requires Laravel v13+');
        }

        EloquentModel::clearBootedModels();
    }

    protected function tearDown(): void
    {
        if (! self::supportsModelAttributes()) {
            parent::tearDown();

            return;
        }

        AttributedDocument::truncate();
        AttributedAuthor::truncate();
        AttributedIntegerKeyDocument::truncate();
        AttributedGuardedDocument::truncate();
        AttributedUnguardedDocument::truncate();

        parent::tearDown();
    }

    private static function supportsModelAttributes(): bool
    {
        return method_exists(EloquentModel::class, 'initializeModelAttributes');
    }

    public function testTableAttributeSetsCollectionName(): void
    {
        self::assertSame('custom_collection', (new AttributedDocument())->getTable());
    }

    public function testTableAttributeSetsCollectionNameOnDocumentModelTrait(): void
    {
        self::assertSame('custom_collection', (new AttributedTraitDocument())->getTable());
    }

    public function testTableAttributeSetsPrimaryKey(): void
    {
        self::assertSame('custom_id', (new AttributedDocument())->getKeyName());
    }

    public function testTableAttributeSetsKeyType(): void
    {
        self::assertSame('int', (new AttributedIntegerKeyDocument())->getKeyType());
    }

    public function testKeyTypePropertyTakesPrecedenceOverTableAttribute(): void
    {
        self::assertSame('string', (new AttributedDeclaredKeyTypeDocument())->getKeyType());
    }

    public function testKeyTypeDefaultsToStringWithoutTableAttribute(): void
    {
        self::assertSame('string', (new AttributedGuardedDocument())->getKeyType());
    }

    public function testTableAttributeSetsIncrementing(): void
    {
        self::assertFalse((new AttributedDocument())->getIncrementing());
    }

    public function testTableAttributeSetsDateFormat(): void
    {
        self::assertSame('U', (new AttributedDocument())->getDateFormat());
    }

    public function testWithoutTimestampsAttributeDisablesTimestamps(): void
    {
        self::assertFalse((new AttributedDocument())->usesTimestamps());
    }

    public function testConnectionAttributeSetsConnectionName(): void
    {
        self::assertSame('mongodb', (new AttributedDocument())->getConnectionName());
    }

    public function testFillableAttribute(): void
    {
        self::assertSame(['name', 'title'], (new AttributedDocument())->getFillable());
    }

    public function testGuardedAttribute(): void
    {
        self::assertSame(['secret'], (new AttributedGuardedDocument())->getGuarded());
    }

    public function testUnguardedAttribute(): void
    {
        self::assertSame([], (new AttributedUnguardedDocument())->getGuarded());
    }

    public function testHiddenAttribute(): void
    {
        self::assertSame(['password'], (new AttributedDocument())->getHidden());
    }

    public function testVisibleAttribute(): void
    {
        self::assertSame(['name'], (new AttributedVisibleDocument())->getVisible());
    }

    public function testAppendsAttribute(): void
    {
        $document = new AttributedDocument();
        $document->name = 'Bilbo';

        self::assertSame(['upper_name'], $document->getAppends());
        self::assertArrayHasKey('upper_name', $document->attributesToArray());
    }

    public function testTouchesAttribute(): void
    {
        self::assertSame(['author'], (new AttributedDocument())->getTouchedRelations());
    }

    public function testUseFactoryAndUseModelAttributes(): void
    {
        $factory = AttributedDocument::factory();

        self::assertInstanceOf(AttributedDocumentFactory::class, $factory);
        self::assertSame(AttributedDocument::class, $factory->modelName());
    }

    public function testTableAttributeStoresDocumentsInTheCollection(): void
    {
        AttributedDocument::create(['name' => 'John Doe']);

        $collection = DB::connection('mongodb')->getCollection('custom_collection');

        $this->assertEquals(1, $collection->countDocuments());
    }

    public function testTableAttributeUsesCustomPrimaryKeyWhenSaving(): void
    {
        $document = new AttributedDocument();
        $document->custom_id = 'a-game-of-thrones';
        $document->name = 'A Game of Thrones';
        $document->save();

        $this->assertSame('a-game-of-thrones', $document->getKey());

        $check = AttributedDocument::find('a-game-of-thrones');
        $this->assertInstanceOf(AttributedDocument::class, $check);
        $this->assertSame('custom_id', $check->getKeyName());
        $this->assertSame('A Game of Thrones', $check->name);
    }

    public function testTableAttributeKeyTypeIsAppliedWhenSaving(): void
    {
        $document = new AttributedIntegerKeyDocument();
        $document->id = 12345;
        $document->save();

        $check = AttributedIntegerKeyDocument::find(12345);
        $this->assertInstanceOf(AttributedIntegerKeyDocument::class, $check);
        $this->assertSame('int', get_debug_type($check->getKey()));
    }

    public function testConnectionAttributeResolvesTheConnection(): void
    {
        $document = AttributedDocument::create(['name' => 'John Doe']);

        $this->assertInstanceOf(MongoDBConnection::class, $document->getConnection());
        $this->assertSame('mongodb', $document->getConnection()->getName());
    }

    public function testWithoutTimestampsAttributeDoesNotStoreTimestamps(): void
    {
        AttributedDocument::create(['name' => 'John Doe']);

        $stored = DB::connection('mongodb')->getCollection('custom_collection')->findOne();

        $this->assertArrayNotHasKey('created_at', (array) $stored);
        $this->assertArrayNotHasKey('updated_at', (array) $stored);
    }

    public function testFillableAttributeIsAppliedOnCreate(): void
    {
        $document = AttributedDocument::create(['name' => 'John Doe', 'password' => 'secret']);

        $check = AttributedDocument::find($document->getKey());
        $this->assertSame('John Doe', $check->name);
        $this->assertNull($check->password);
    }

    public function testUnguardedAttributeAllowsMassAssignment(): void
    {
        $document = AttributedUnguardedDocument::create(['name' => 'John Doe', 'secret' => 'hidden']);

        $check = AttributedUnguardedDocument::find($document->getKey());
        $this->assertSame('hidden', $check->secret);
    }

    public function testHiddenAndAppendsAttributesAreAppliedToRetrievedDocuments(): void
    {
        $document = new AttributedDocument();
        $document->name = 'John Doe';
        $document->password = 'secret';
        $document->save();

        $array = AttributedDocument::find($document->getKey())->toArray();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertSame('JOHN DOE', $array['upper_name']);
    }

    public function testTouchesAttributeUpdatesTheRelatedDocument(): void
    {
        $author = AttributedAuthor::create(['name' => 'George R. R. Martin']);

        $document = new AttributedDocument();
        $document->name = 'A Game of Thrones';
        $document->author_id = $author->getKey();
        $document->save();

        $old = $author->updated_at;
        sleep(1);

        $document->name = 'A Clash of Kings';
        $document->save();

        $this->assertNotEquals($old, AttributedAuthor::find($author->getKey())->updated_at);
    }

    public function testUseFactoryAttributeCreatesPersistedDocument(): void
    {
        $document = AttributedDocument::factory()->create();

        $this->assertSame('Bilbo', $document->name);
        $this->assertEquals(1, AttributedDocument::count());
    }
}
