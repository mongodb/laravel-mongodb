<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Query;

use LogicException;
use MongoDB\Builder\Pipeline as BuilderPipeline;
use MongoDB\Builder\Type\StageInterface;
use stdClass;

use function array_key_first;
use function array_shift;
use function array_values;
use function count;
use function is_array;
use function is_numeric;
use function is_string;
use function iterator_count;
use function sprintf;
use function str_starts_with;

/** @internal */
final class Pipeline
{
    private const OBJECT_TYPE_MAP = ['root' => 'object', 'document' => 'array'];

    private BuilderPipeline $stages;

    /** @var list<string> */
    private array $temporaryFields = [];

    public function __construct()
    {
        $this->stages = new BuilderPipeline();
    }

    /** @param array<string, mixed>|StageInterface $stage */
    public function addStage(array|StageInterface $stage): void
    {
        $this->stages = new BuilderPipeline($this->stages, $stage);
    }

    public function unsetTemporaryField(string $field): void
    {
        $this->temporaryFields[] = $field;
    }

    public function mergeFrom(self $other): void
    {
        $this->stages = new BuilderPipeline($this->stages, $other->stages);

        foreach ($other->temporaryFields as $field) {
            $this->temporaryFields[] = $field;
        }
    }

    public function isEmpty(): bool
    {
        return iterator_count($this->stages) === 0;
    }

    public function assertWritable(): void
    {
        if ($this->isEmpty()) {
            return;
        }

        throw new LogicException('A query carrying relationship aggregate stages cannot delete or update documents.');
    }

    /**
     * @param array<string, mixed[]> $command
     *
     * @return array<string, mixed[]>
     */
    public function applyTo(array $command): array
    {
        if ($this->isEmpty()) {
            return $command;
        }

        $method = (string) array_key_first($command);

        return match ($method) {
            'find' => $this->findAsPipeline($command['find']),
            'countDocuments' => $this->countAsPipeline($command['countDocuments']),
            'aggregate' => $this->aggregateWithStages($command['aggregate']),
            default => throw new LogicException(sprintf(
                'Pipeline stages cannot be combined with a [%s] query.',
                $method,
            )),
        };
    }

    /**
     * @param array<mixed> $find
     *
     * @return array<string, mixed[]>
     */
    private function findAsPipeline(array $find): array
    {
        [$wheres, $options] = $find + [[], []];
        $wheres = is_array($wheres) ? $wheres : [];
        $options = is_array($options) ? $options : [];

        [$before, $after] = $this->splitFilter($wheres);

        $pipeline = [
            ...$this->matchStage($before),
            ...$this->stages,
            ...$this->matchStage($after),
            ...$this->sortStage($options),
            ...$this->pagingStages($options),
            ...$this->projectionStages($options),
            ...$this->unsetStage(),
        ];

        return ['aggregate' => [$pipeline, $this->pipelineOptions($options)]];
    }

    /**
     * @param array<mixed> $count
     *
     * @return array<string, mixed[]>
     */
    private function countAsPipeline(array $count): array
    {
        [$wheres, $options] = $count + [[], []];
        $wheres = is_array($wheres) ? $wheres : [];
        $options = is_array($options) ? $options : [];

        [$before, $after] = $this->splitFilter($wheres);

        $pipeline = [
            ...$this->matchStage($before),
            ...$this->stages,
            ...$this->matchStage($after),
            ...$this->pagingStages($options),
            ['$count' => 'aggregate'],
        ];

        return ['aggregate' => [$pipeline, ['typeMap' => self::OBJECT_TYPE_MAP] + $this->pipelineOptions($options)]];
    }

    /**
     * @param array<mixed> $aggregate
     *
     * @return array<string, mixed[]>
     */
    private function aggregateWithStages(array $aggregate): array
    {
        [$pipeline, $options] = $aggregate + [[], []];
        $pipeline = is_array($pipeline) ? array_values($pipeline) : [];

        $leadingMatch = isset($pipeline[0]['$match']) ? (array) array_shift($pipeline)['$match'] : [];
        [$before, $after] = $this->splitFilter($leadingMatch);

        return [
            'aggregate' => [
                [
                    ...$this->matchStage($before),
                    ...$this->stages,
                    ...$this->matchStage($after),
                    ...$pipeline,
                    ...$this->unsetStage(),
                ],
                $options,
            ],
        ];
    }

    /**
     * @param array<array-key, mixed> $filter
     *
     * @return array{array<array-key, mixed>, array<array-key, mixed>}
     */
    private function splitFilter(array $filter): array
    {
        if ($filter === []) {
            return [[], []];
        }

        $before = [];
        $after = [];
        foreach ($this->conjuncts($filter) as $condition) {
            if ($this->referencesTemporaryField($condition)) {
                $after[] = $condition;
                continue;
            }

            $before[] = $condition;
        }

        return [$this->combine($before), $this->combine($after)];
    }

    /**
     * @param array<array-key, mixed> $filter
     *
     * @return list<array<array-key, mixed>>
     */
    private function conjuncts(array $filter): array
    {
        if (! isset($filter['$and']) || ! is_array($filter['$and'])) {
            return [$filter];
        }

        $siblings = $filter;
        unset($siblings['$and']);

        return [...$this->rootConditions($siblings), ...array_values($filter['$and'])];
    }

    /**
     * @param array<array-key, mixed> $siblings
     *
     * @return list<array<array-key, mixed>>
     */
    private function rootConditions(array $siblings): array
    {
        $conditions = [];

        foreach ($siblings as $key => $value) {
            $conditions[] = [$key => $value];
        }

        return $conditions;
    }

    /**
     * @param list<array<array-key, mixed>> $conditions
     *
     * @return array<array-key, mixed>
     */
    private function combine(array $conditions): array
    {
        return match (count($conditions)) {
            0 => [],
            1 => $conditions[0],
            default => ['$and' => $conditions],
        };
    }

    private function referencesTemporaryField(mixed $filter): bool
    {
        if (is_string($filter)) {
            return $this->referencesTemporaryFieldPath($filter);
        }

        if (! is_array($filter)) {
            return false;
        }

        foreach ($filter as $key => $value) {
            if (is_string($key) && $this->referencesTemporaryFieldKey($key)) {
                return true;
            }

            if ($this->referencesTemporaryField($value)) {
                return true;
            }
        }

        return false;
    }

    private function referencesTemporaryFieldKey(string $key): bool
    {
        foreach ($this->temporaryFields as $field) {
            if ($key === $field || str_starts_with($key, $field . '.')) {
                return true;
            }
        }

        return false;
    }

    private function referencesTemporaryFieldPath(string $path): bool
    {
        foreach ($this->temporaryFields as $field) {
            if ($path === '$' . $field || str_starts_with($path, '$' . $field . '.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $wheres
     *
     * @return list<array<string, mixed>>
     */
    private function matchStage(array $wheres): array
    {
        return $wheres === [] ? [] : [['$match' => $wheres]];
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return list<array<string, mixed>>
     */
    private function sortStage(array $options): array
    {
        $sort = $this->userSort($options);

        if ($sort === []) {
            return [];
        }

        if (isset($sort['$natural'])) {
            throw new LogicException('A "$natural" sort cannot be compiled into an aggregation $sort stage, so it cannot be combined with a query that carries pipeline stages.');
        }

        return [['$sort' => $sort]];
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, int>
     */
    private function userSort(array $options): array
    {
        $sort = $options['sort'] ?? null;

        return match (true) {
            $sort instanceof stdClass => (array) $sort,
            is_array($sort) => $sort,
            default => [],
        };
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return list<array<string, mixed>>
     */
    private function pagingStages(array $options): array
    {
        return [
            ...$this->stageIfNotZero($options, 'skip'),
            ...$this->stageIfNotZero($options, 'limit'),
        ];
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return list<array<string, mixed>>
     */
    private function stageIfNotZero(array $options, string $option): array
    {
        $value = $options[$option] ?? null;

        if ($value === null || (is_numeric($value) && (float) $value === 0.0)) {
            return [];
        }

        return [['$' . $option => $value]];
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return list<array<string, mixed>>
     */
    private function projectionStages(array $options): array
    {
        $projection = $options['projection'] ?? null;

        if ($projection instanceof stdClass || is_array($projection)) {
            return Projection::stages($projection);
        }

        return [];
    }

    /** @return list<array<string, mixed>> */
    private function unsetStage(): array
    {
        return $this->temporaryFields === [] ? [] : [['$unset' => $this->temporaryFields]];
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function pipelineOptions(array $options): array
    {
        unset($options['sort'], $options['skip'], $options['limit'], $options['projection']);

        return $options;
    }
}
