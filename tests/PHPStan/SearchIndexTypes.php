<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\PHPStan;

use MongoDB\Laravel\Schema\Blueprint;

/**
 * PHPStan type-level tests for search index definitions.
 * These functions are never executed at runtime — they exist to let PHPStan
 * validate that complex index definitions match the declared @phpstan-type shapes.
 *
 * Examples are taken verbatim from the MongoDB Atlas documentation:
 *
 * @link https://www.mongodb.com/docs/atlas/atlas-search/define-field-mappings.md
 * @link https://www.mongodb.com/docs/atlas/atlas-vector-search/vector-search-type.md
 *
 * @phpstan-import-type TypeSearchIndexDefinition from Blueprint
 * @phpstan-import-type TypeVectorSearchIndexDefinition from Blueprint
 */
final class SearchIndexTypes
{
    /** @phpstan-param TypeSearchIndexDefinition $definition */
    public static function assertSearchIndexDefinition(array $definition): void
    {
    }

    /** @phpstan-param TypeVectorSearchIndexDefinition $definition */
    public static function assertVectorSearchIndexDefinition(array $definition): void
    {
    }

    public static function searchIndexExamples(): void
    {
        // Static mapping with nested document, multi-analyzer string, ignoreAbove
        // Source: https://www.mongodb.com/docs/atlas/atlas-search/define-field-mappings.md
        self::assertSearchIndexDefinition([
            'analyzer' => 'lucene.standard',
            'searchAnalyzer' => 'lucene.standard',
            'mappings' => [
                'dynamic' => false,
                'fields' => [
                    'awards' => [
                        'type' => 'document',
                        'fields' => [
                            'wins' => ['type' => 'number'],
                            'nominations' => ['type' => 'number', 'representation' => 'int64'],
                            'text' => ['type' => 'string', 'analyzer' => 'lucene.english', 'ignoreAbove' => 255],
                        ],
                    ],
                    'title' => [
                        'type' => 'string',
                        'analyzer' => 'lucene.whitespace',
                        'multi' => [
                            'mySecondaryAnalyzer' => ['type' => 'string', 'analyzer' => 'lucene.french'],
                        ],
                    ],
                    'genres' => ['type' => 'string', 'analyzer' => 'lucene.standard'],
                ],
            ],
        ]);

        // Synonyms
        // Source: https://www.mongodb.com/docs/atlas/atlas-search/synonyms.md
        self::assertSearchIndexDefinition([
            'mappings' => [
                'dynamic' => false,
                'fields' => [
                    'plot' => ['type' => 'string', 'analyzer' => 'lucene.english'],
                ],
            ],
            'synonyms' => [
                [
                    'analyzer' => 'lucene.english',
                    'name' => 'my_synonyms',
                    'source' => ['collection' => 'synonymous_terms'],
                ],
            ],
        ]);

        // storedSource with include
        // Source: https://www.mongodb.com/docs/atlas/atlas-search/stored-source-definition.md
        self::assertSearchIndexDefinition([
            'mappings' => ['dynamic' => true],
            'storedSource' => ['include' => ['title', 'awards.wins']],
        ]);

        // storedSource with exclude
        // Source: https://www.mongodb.com/docs/atlas/atlas-search/stored-source-definition.md
        self::assertSearchIndexDefinition([
            'mappings' => ['dynamic' => true],
            'storedSource' => ['exclude' => ['directors', 'imdb.rating']],
        ]);

        // Dynamic typeSet-based mapping
        // Source: https://www.mongodb.com/docs/atlas/atlas-search/define-field-mappings.md
        self::assertSearchIndexDefinition([
            'analyzer' => 'lucene.standard',
            'searchAnalyzer' => 'lucene.standard',
            'mappings' => [
                'dynamic' => ['typeSet' => 'indexedTypes'],
                'fields' => [
                    'plot' => [],
                ],
            ],
            'typeSets' => [
                [
                    'name' => 'indexedTypes',
                    'types' => [
                        ['type' => 'token'],
                        ['type' => 'number'],
                    ],
                ],
            ],
        ]);

        // typeSets with autocomplete and multi-analyzer string
        // Source: https://www.mongodb.com/docs/atlas/atlas-search/define-field-mappings.md
        self::assertSearchIndexDefinition([
            'analyzer' => 'lucene.standard',
            'searchAnalyzer' => 'lucene.standard',
            'mappings' => [
                'dynamic' => false,
                'fields' => [
                    'awards' => ['type' => 'document', 'fields' => []],
                ],
            ],
            'typeSets' => [
                [
                    'name' => 'movieAwards',
                    'types' => [
                        [
                            'type' => 'string',
                            'multi' => [
                                'english' => ['type' => 'string', 'analyzer' => 'lucene.english'],
                                'french'  => ['type' => 'string', 'analyzer' => 'lucene.french'],
                            ],
                        ],
                        ['type' => 'number'],
                        [
                            'type' => 'autocomplete',
                            'analyzer' => 'lucene.standard',
                            'tokenization' => 'edgeGram',
                            'minGrams' => 3,
                            'maxGrams' => 5,
                            'foldDiacritics' => false,
                        ],
                    ],
                ],
            ],
        ]);

        // String field with all options including similarity
        // Source: https://www.mongodb.com/docs/atlas/atlas-search/field-types/string-type.md
        self::assertSearchIndexDefinition([
            'mappings' => [
                'dynamic' => false,
                'fields' => [
                    'plot' => [
                        'type' => 'string',
                        'analyzer' => 'lucene.english',
                        'searchAnalyzer' => 'lucene.standard',
                        'indexOptions' => 'offsets',
                        'store' => true,
                        'ignoreAbove' => 255,
                        'norms' => 'omit',
                        'similarity' => ['type' => 'bm25'],
                    ],
                ],
            ],
        ]);

        // Autocomplete field with all options including similarity
        // Source: https://www.mongodb.com/docs/atlas/atlas-search/field-types/autocomplete-type.md
        self::assertSearchIndexDefinition([
            'mappings' => [
                'dynamic' => false,
                'fields' => [
                    'title' => [
                        'type' => 'autocomplete',
                        'analyzer' => 'lucene.standard',
                        'tokenization' => 'edgeGram',
                        'minGrams' => 2,
                        'maxGrams' => 15,
                        'foldDiacritics' => true,
                        'similarity' => ['type' => 'stableTfl'],
                    ],
                ],
            ],
        ]);
    }

    public static function vectorSearchIndexExamples(): void
    {
        // Vector + quantization + HNSW + two filter fields
        // Source: https://www.mongodb.com/docs/atlas/atlas-vector-search/vector-search-type.md
        self::assertVectorSearchIndexDefinition([
            'fields' => [
                [
                    'type' => 'vector',
                    'path' => 'plot_embedding_voyage_3_large',
                    'numDimensions' => 2048,
                    'similarity' => 'dotProduct',
                    'quantization' => 'scalar',
                    'indexingMethod' => 'hnsw',
                ],
                ['type' => 'filter', 'path' => 'genres'],
                ['type' => 'filter', 'path' => 'year'],
            ],
        ]);

        // Vector with hnswOptions (full syntax from docs)
        // Source: https://www.mongodb.com/docs/atlas/atlas-vector-search/vector-search-type.md
        self::assertVectorSearchIndexDefinition([
            'fields' => [
                [
                    'type' => 'vector',
                    'path' => 'plot_embedding',
                    'numDimensions' => 1536,
                    'similarity' => 'cosine',
                    'quantization' => 'none',
                    'indexingMethod' => 'hnsw',
                    'hnswOptions' => [
                        'maxEdges' => 32,
                        'numEdgeCandidates' => 200,
                    ],
                ],
                ['type' => 'filter', 'path' => 'genres'],
            ],
        ]);
    }
}
