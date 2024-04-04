<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Query;

use Illuminate\Support\Collection as LaravelCollection;
use Illuminate\Support\LazyCollection;
use InvalidArgumentException;
use Iterator;
use MongoDB\Builder\BuilderEncoder;
use MongoDB\Builder\Stage\FluentFactoryTrait;
use MongoDB\Collection as MongoDBCollection;
use MongoDB\Driver\CursorInterface;
use MongoDB\Laravel\Collection as LaravelMongoDBCollection;

use function array_replace;
use function collect;
use function sprintf;
use function str_starts_with;

class AggregationBuilder
{
    use FluentFactoryTrait;

    public function __construct(
        private MongoDBCollection|LaravelMongoDBCollection $collection,
        private readonly array $options = [],
    ) {
    }

    /**
     * Add a stage without using the builder. Necessary if the stage is built
     * outside the builder, or it is not yet supported by the library.
     */
    public function addRawStage(string $operator, mixed $value): static
    {
        if (! str_starts_with($operator, '$')) {
            throw new InvalidArgumentException(sprintf('The stage name "%s" is invalid. It must start with a "$" sign.', $operator));
        }

        $this->pipeline[] = [$operator => $value];

        return $this;
    }

    /**
     * Execute the aggregation pipeline and return the results.
     */
    public function get(array $options = []): LaravelCollection|LazyCollection
    {
        $cursor = $this->execute($options);

        return collect($cursor->toArray());
    }

    /**
     * Execute the aggregation pipeline and return the results in a lazy collection.
     */
    public function cursor($options = []): LazyCollection
    {
        $cursor = $this->execute($options);

        return LazyCollection::make(function () use ($cursor) {
            foreach ($cursor as $item) {
                yield $item;
            }
        });
    }

    /**
     * Execute the aggregation pipeline and return the first result.
     */
    public function first(array $options = []): mixed
    {
        return (clone $this)
            ->limit(1)
            ->get($options)
            ->first();
    }

    /**
     * Execute the aggregation pipeline and return MongoDB cursor.
     */
    private function execute(array $options): CursorInterface&Iterator
    {
        $encoder = new BuilderEncoder();
        $pipeline = $encoder->encode($this->getPipeline());

        $options = array_replace(
            ['typeMap' => ['root' => 'array', 'document' => 'array']],
            $this->options,
            $options,
        );

        return $this->collection->aggregate($pipeline, $options);
    }
}
