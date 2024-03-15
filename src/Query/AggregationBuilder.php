<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Query;

use Illuminate\Support\Collection as LaravelCollection;
use MongoDB\Builder\BuilderEncoder;
use MongoDB\Builder\Stage\FluentFactoryTrait;
use MongoDB\Collection as MongoDBCollection;
use MongoDB\Laravel\Collection as LaravelMongoDBCollection;

use function array_replace;
use function collect;

final class AggregationBuilder
{
    use FluentFactoryTrait;

    public function __construct(
        private MongoDBCollection|LaravelMongoDBCollection $collection,
        array $pipeline = [],
        private array $options = [],
    ) {
        $this->pipeline = $pipeline;
    }

    /**
     * Add a stage without using the builder. Necessary if the stage is built
     * outside the builder, or it is not yet supported by the library.
     */
    public function addRawStage(string $operator, mixed $value): static
    {
        $this->pipeline[] = [$operator => $value];

        return $this;
    }

    /**
     * Execute the aggregation pipeline and return the results.
     */
    public function get(array $options = []): LaravelCollection
    {
        $encoder = new BuilderEncoder();
        $pipeline = $encoder->encode($this->getPipeline());

        $options = array_replace(
            ['typeMap' => ['root' => 'array', 'document' => 'array']],
            $this->options,
            $options,
        );

        return collect($this->collection->aggregate($pipeline, $options)->toArray());
    }
}
