<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Query;

use Illuminate\Support\Collection as LaravelCollection;
use MongoDB\Builder\BuilderEncoder;
use MongoDB\Builder\Stage\FluentFactory;
use MongoDB\Laravel\Collection;

use function array_replace;
use function collect;

final class PipelineBuilder extends FluentFactory
{
    public function __construct(
        array $pipeline,
        private Collection $collection,
        private array $options,
    ) {
        $this->pipeline = $pipeline;
    }

    /**
     * Execute the aggregation pipeline and return the results.
     */
    public function get(): LaravelCollection
    {
        $encoder = new BuilderEncoder();
        $pipeline = $encoder->encode($this->getPipeline());

        $options = array_replace(
            ['typeMap' => ['root' => 'array', 'document' => 'array']],
            $this->options,
        );

        return collect($this->collection->aggregate($pipeline, $options)->toArray());
    }
}
