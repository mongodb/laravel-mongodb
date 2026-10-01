<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Query;

use Illuminate\Support\Collection as LaravelCollection;
use Illuminate\Support\LazyCollection;
use InvalidArgumentException;
use Iterator;
use MongoDB\Builder\BuilderEncoder;
use MongoDB\Builder\Stage\FluentFactoryTrait;
use MongoDB\Collection;
use MongoDB\Driver\CursorInterface;
use MongoDB\Laravel\Encryption\AutoEncryption;
use stdClass;

use function array_intersect;
use function array_keys;
use function array_replace;
use function collect;
use function end;
use function sprintf;
use function str_starts_with;

class AggregationBuilder
{
    use FluentFactoryTrait;

    private const TERMINAL_STAGES = ['$out', '$merge'];

    public function __construct(
        private Collection $collection,
        private readonly array $options = [],
        private readonly bool $hidesSafeContent = false,
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
        $pipeline = $this->hideSafeContent($encoder->encode($this->getPipeline()));

        $options = array_replace(
            ['typeMap' => ['root' => 'array', 'document' => 'array']],
            $this->options,
            $options,
        );

        return $this->collection->aggregate($pipeline, $options);
    }

    /**
     * A "$out" or "$merge" returns no documents and must stay last.
     *
     * @param  list<array<string, mixed>|stdClass> $pipeline
     *
     * @return list<array<string, mixed>|stdClass>
     */
    private function hideSafeContent(array $pipeline): array
    {
        if (! $this->hidesSafeContent || $this->writesOut($pipeline)) {
            return $pipeline;
        }

        return [...$pipeline, ['$unset' => AutoEncryption::SAFE_CONTENT_FIELD]];
    }

    /** @param list<array<string, mixed>|stdClass> $pipeline */
    private function writesOut(array $pipeline): bool
    {
        $last = end($pipeline);

        if ($last === false) {
            return false;
        }

        return array_intersect(array_keys((array) $last), self::TERMINAL_STAGES) !== [];
    }
}
