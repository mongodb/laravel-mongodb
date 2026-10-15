<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Query;

use LogicException;

use function array_key_first;
use function array_shift;
use function array_values;
use function is_array;
use function sprintf;

/** @internal */
final class Pipeline
{
    private const OBJECT_TYPE_MAP = ['root' => 'object', 'document' => 'array'];

    /** @var list<array<string, mixed>> */
    private array $stages = [];

    /** @var array<string, int> */
    private array $computedSorts = [];

    /** @var list<string> */
    private array $temporaryFields = [];

    /** @param array<string, mixed> $stage */
    public function addStage(array $stage): void
    {
        $this->stages[] = $stage;
    }

    public function sortByComputedField(string $field, int $direction): void
    {
        $this->computedSorts[$field] = $direction;
    }

    public function unsetTemporaryField(string $field): void
    {
        $this->temporaryFields[] = $field;
    }

    /**
     * @param array<string, mixed[]> $command
     *
     * @return array<string, mixed[]>
     */
    public function applyTo(array $command): array
    {
        if ($this->stages === []) {
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
        $options = is_array($options) ? $options : [];

        $pipeline = [
            ...$this->matchStage(is_array($wheres) ? $wheres : []),
            ...$this->stages,
            ...$this->sortStage($options),
            ...$this->pagingStages($options),
            ...$this->projectionStage($options),
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
        $options = is_array($options) ? $options : [];

        $pipeline = [
            ...$this->matchStage(is_array($wheres) ? $wheres : []),
            ...$this->stages,
            ['$count' => 'aggregate'],
        ];

        return ['aggregate' => [$pipeline, ['typeMap' => self::OBJECT_TYPE_MAP] + $options]];
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

        $leadingMatch = isset($pipeline[0]['$match']) ? [array_shift($pipeline)] : [];

        return [
            'aggregate' => [
                [...$leadingMatch, ...$this->stages, ...$pipeline],
                $options,
            ],
        ];
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
        $sort = $this->computedSorts + $this->userSort($options);

        return $sort === [] ? [] : [['$sort' => $sort]];
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, int>
     */
    private function userSort(array $options): array
    {
        return is_array($options['sort'] ?? null) ? $options['sort'] : [];
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return list<array<string, mixed>>
     */
    private function pagingStages(array $options): array
    {
        return [
            ...$this->stageIfSet($options, 'skip'),
            ...$this->stageIfSet($options, 'limit'),
        ];
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return list<array<string, mixed>>
     */
    private function stageIfSet(array $options, string $option): array
    {
        return isset($options[$option]) ? [['$' . $option => $options[$option]]] : [];
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return list<array<string, mixed>>
     */
    private function projectionStage(array $options): array
    {
        return isset($options['projection']) && is_array($options['projection'])
            ? [['$project' => $options['projection']]]
            : [];
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
