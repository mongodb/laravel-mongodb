<?php

namespace MongoDB\Laravel\Tests;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection as LaravelCollection;
use Illuminate\Support\Facades\Schema;
use MongoDB\Builder\Query;
use MongoDB\Builder\Search;
use MongoDB\Collection as MongoDBCollection;
use MongoDB\Driver\Exception\ServerException;
use MongoDB\Laravel\Schema\Builder;
use MongoDB\Laravel\Tests\Models\Book;
use PHPUnit\Framework\Attributes\Group;

use function array_map;
use function assert;
use function getenv;
use function mt_getrandmax;
use function rand;
use function range;
use function srand;
use function str_contains;
use function usort;

#[Group('atlas-search')]
class AtlasSearchTest extends TestCase
{
    use AtlasSearchIndexManagement;

    /** Vectors generated for the fixture, shared across all tests in this class. */
    private static array $vectors = [];

    /** Whether the expensive Atlas Search fixture has already been loaded for this class. */
    private static bool $fixtureLoaded = false;

    /** Whether the server reported that Atlas Search is not supported. */
    private static bool $atlasSearchNotSupported = false;

    /** Cached collection handle so tearDownAfterClass() can drop it without a bootstrapped app. */
    private static ?MongoDBCollection $collection = null;

    protected function setUp(): void
    {
        parent::setUp();

        // The fixture is read-only for every test except the auto-embedding one, which
        // cleans up its own extra index. Load it once per class instead of per test:
        // recreating the collection, inserting 20 documents, and waiting on 3 search
        // indexes is the most expensive part of this file and brings no isolation gain.
        if (self::$atlasSearchNotSupported) {
            self::markTestSkipped('Atlas Search not supported.');
        }

        if (! self::$fixtureLoaded) {
            $this->loadAtlasSearchFixture();
            self::$fixtureLoaded = true;
        }
    }

    private function loadAtlasSearchFixture(): void
    {
        $collection = $this->getConnection('mongodb')->getCollection('books');
        assert($collection instanceof MongoDBCollection);
        self::$collection = $collection;
        $collection->drop();

        Book::insert(self::addVector([
            ['title' => 'Introduction to Algorithms'],
            ['title' => 'Clean Code: A Handbook of Agile Software Craftsmanship'],
            ['title' => 'Design Patterns: Elements of Reusable Object-Oriented Software'],
            ['title' => 'The Pragmatic Programmer: Your Journey to Mastery'],
            ['title' => 'Artificial Intelligence: A Modern Approach'],
            ['title' => 'Structure and Interpretation of Computer Programs'],
            ['title' => 'Code Complete: A Practical Handbook of Software Construction'],
            ['title' => 'The Art of Computer Programming'],
            ['title' => 'Computer Networks'],
            ['title' => 'Operating System Concepts'],
            ['title' => 'Database System Concepts'],
            ['title' => 'Compilers: Principles, Techniques, and Tools'],
            ['title' => 'Introduction to the Theory of Computation'],
            ['title' => 'Modern Operating Systems'],
            ['title' => 'Computer Organization and Design'],
            ['title' => 'The Mythical Man-Month: Essays on Software Engineering'],
            ['title' => 'Algorithms'],
            ['title' => 'Understanding Machine Learning: From Theory to Algorithms'],
            ['title' => 'Deep Learning'],
            ['title' => 'Pattern Recognition and Machine Learning'],
        ]));

        try {
            $this->waitForSearchIndexesDropped($collection);

            $collection->createSearchIndex([
                'mappings' => [
                    'fields' => [
                        'title' => [
                            ['type' => 'string', 'analyzer' => 'lucene.english'],
                            ['type' => 'autocomplete', 'analyzer' => 'lucene.english'],
                            ['type' => 'token'],
                        ],
                    ],
                ],
            ]);

            $collection->createSearchIndex([
                'mappings' => ['dynamic' => true],
            ], ['name' => 'dynamic_search']);

            $collection->createSearchIndex([
                'fields' => [
                    ['type' => 'vector', 'numDimensions' => 4, 'path' => 'vector4', 'similarity' => 'cosine'],
                    ['type' => 'vector', 'numDimensions' => 32, 'path' => 'vector32', 'similarity' => 'euclidean'],
                    ['type' => 'filter', 'path' => 'title'],
                ],
            ], ['name' => 'vector', 'type' => 'vectorSearch']);
        } catch (ServerException $e) {
            if (Builder::isAtlasSearchNotSupportedException($e)) {
                self::$atlasSearchNotSupported = true;
                self::markTestSkipped('Atlas Search not supported. ' . $e->getMessage());
            }

            throw $e;
        }

        $this->waitForSearchIndexesReady($collection);
    }

    public static function tearDownAfterClass(): void
    {
        // Drop the shared collection once the whole class is done. The cached Collection
        // handle carries its own driver Manager, so this works without a bootstrapped app.
        self::$collection?->drop();

        self::$collection = null;
        self::$fixtureLoaded = false;
        self::$vectors = [];
        self::$atlasSearchNotSupported = false;

        parent::tearDownAfterClass();
    }

    public function testGetIndexes()
    {
        $indexes = Schema::getIndexes('books');

        self::assertIsArray($indexes);
        self::assertCount(4, $indexes);

        // Order of indexes is not guaranteed
        usort($indexes, fn ($a, $b) => $a['name'] <=> $b['name']);

        $expected = [
            [
                'name' => '_id_',
                'columns' => ['_id'],
                'primary' => true,
                'type' => null,
                'unique' => false,
            ],
            [
                'name' => 'default',
                'columns' => ['title'],
                'type' => 'search',
                'primary' => false,
                'unique' => false,
            ],
            [
                'name' => 'dynamic_search',
                'columns' => ['dynamic'],
                'type' => 'search',
                'primary' => false,
                'unique' => false,
            ],
            [
                'name' => 'vector',
                'columns' => ['vector4', 'vector32', 'title'],
                'type' => 'vectorSearch',
                'primary' => false,
                'unique' => false,
            ],
        ];

        self::assertSame($expected, $indexes);
    }

    public function testEloquentBuilderSearch()
    {
        $results = Book::search(
            sort: ['title' => 1],
            operator: Search::text('title', 'systems'),
        );

        self::assertInstanceOf(EloquentCollection::class, $results);
        self::assertCount(3, $results);
        self::assertInstanceOf(Book::class, $results->first());
        self::assertSame([
            'Database System Concepts',
            'Modern Operating Systems',
            'Operating System Concepts',
        ], $results->pluck('title')->all());
    }

    public function testDatabaseBuilderSearch()
    {
        $results = $this->getConnection('mongodb')->table('books')
            ->search(Search::text('title', 'systems'), sort: ['title' => 1]);

        self::assertInstanceOf(LaravelCollection::class, $results);
        self::assertCount(3, $results);
        self::assertIsArray($results->first());
        self::assertSame([
            'Database System Concepts',
            'Modern Operating Systems',
            'Operating System Concepts',
        ], $results->pluck('title')->all());
    }

    public function testEloquentBuilderAutocomplete()
    {
        $results = Book::autocomplete('title', 'system');

        self::assertInstanceOf(LaravelCollection::class, $results);
        self::assertCount(3, $results);
        self::assertSame([
            'Database System Concepts',
            'Modern Operating Systems',
            'Operating System Concepts',
        ], $results->sort()->values()->all());
    }

    public function testDatabaseBuilderAutocomplete()
    {
        $results = $this->getConnection('mongodb')->table('books')
            ->autocomplete('title', 'system');

        self::assertInstanceOf(LaravelCollection::class, $results);
        self::assertCount(3, $results);
        self::assertSame([
            'Database System Concepts',
            'Modern Operating Systems',
            'Operating System Concepts',
        ], $results->sort()->values()->all());
    }

    public function testDatabaseBuilderVectorSearch()
    {
        $results = $this->getConnection('mongodb')->table('books')
            ->vectorSearch(
                index: 'vector',
                path: 'vector4',
                queryVector: self::$vectors[7], // This is an exact match of the vector
                limit: 4,
                exact: true,
            );

        self::assertInstanceOf(LaravelCollection::class, $results);
        self::assertCount(4, $results);
        self::assertSame('The Art of Computer Programming', $results->first()['title']);
        self::assertSame(1.0, $results->first()['vectorSearchScore']);
    }

    public function testEloquentBuilderVectorSearch()
    {
        $results = Book::vectorSearch(
            index: 'vector',
            path: 'vector4',
            queryVector: self::$vectors[7],
            limit: 5,
            numCandidates: 15,
            // excludes the exact match
            filter: Query::query(
                title: Query::ne('The Art of Computer Programming'),
            ),
        );

        self::assertInstanceOf(EloquentCollection::class, $results);
        self::assertCount(5, $results);
        self::assertInstanceOf(Book::class, $results->first());
        self::assertNotSame('The Art of Computer Programming', $results->first()->title);
        self::assertSame('The Mythical Man-Month: Essays on Software Engineering', $results->first()->title);
        self::assertThat(
            $results->first()->vectorSearchScore,
            self::logicalAnd(self::isType('float'), self::greaterThan(0.9), self::lessThan(1.0)),
        );
    }

    public function testEloquentBuilderVectorSearchAutoEmbedding()
    {
        if (getenv('VOYAGE_API_KEY') === false || getenv('VOYAGE_API_KEY') === '') {
            self::markTestSkipped('Auto embedding requires VOYAGE_API_KEY to be set on the Atlas cluster.');
        }

        $collection = $this->getConnection('mongodb')->getCollection('books');
        assert($collection instanceof MongoDBCollection);

        try {
            Schema::table('books', function ($table) {
                $table->vectorSearchIndex([
                    'fields' => [
                        ['type' => 'autoEmbed', 'modality' => 'text', 'path' => 'title', 'model' => 'voyage-4-large'],
                    ],
                ], 'auto_embed');
            });
        } catch (ServerException $e) {
            if (str_contains($e->getMessage(), 'not registered')) {
                self::markTestSkipped('Auto embedding requires an Atlas cluster with a registered embedding model. Set VOYAGE_API_KEY.');
            }

            throw $e;
        }

        try {
            $this->waitForSearchIndexesReady($collection);

            // Query with a lighter model (voyage-4-lite) than the indexing model (voyage-4-large);
            // all voyage-4 embeddings are compatible.
            $results = Book::vectorSearch(
                index: 'auto_embed',
                path: 'title',
                query: 'machine learning textbook',
                model: 'voyage-4-lite',
                limit: 3,
            );

            self::assertInstanceOf(EloquentCollection::class, $results);
            self::assertCount(3, $results);
            self::assertInstanceOf(Book::class, $results->first());
            self::assertIsFloat($results->first()->vectorSearchScore);
        } finally {
            // This test mutates the shared fixture by adding an index. Drop it and wait
            // for the deletion to complete so the shared state stays at the 4 indexes
            // the other tests expect, regardless of test execution order. We cannot use
            // waitForSearchIndexesDropped() here: it waits for ALL indexes to be gone,
            // which would time out because the 3 fixture indexes remain.
            $collection->dropSearchIndex('auto_embed');
            $this->waitForSearchIndexDropped($collection, 'auto_embed');
        }
    }

    /** Generate random vectors using fixed seed to make tests deterministic */
    private function addVector(array $items): array
    {
        srand(1);
        foreach ($items as &$item) {
            self::$vectors[] = $item['vector4'] = array_map(fn () => rand() / mt_getrandmax(), range(0, 3));
        }

        return $items;
    }
}
