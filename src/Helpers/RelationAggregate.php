<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Helpers;

use InvalidArgumentException;

use function count;
use function explode;
use function implode;
use function in_array;
use function sprintf;
use function str_starts_with;
use function strtolower;

/** @internal */
final readonly class RelationAggregate
{
    public const FUNCTIONS = ['avg', 'count', 'exists', 'max', 'min', 'sum'];

    private function __construct(
        public string $function,
        public string $column,
        private ?string $arrayPath = null,
    ) {
    }

    /** Supports the Filament bridge. */
    public static function of(string $function, string $column): self
    {
        $function = strtolower($function);

        if (! in_array($function, self::FUNCTIONS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Aggregate function "%s" is not supported by MongoDB. Supported functions are: %s.',
                $function,
                implode(', ', self::FUNCTIONS),
            ));
        }

        if (str_starts_with($column, '$')) {
            throw new InvalidArgumentException(sprintf(
                'The aggregate column name "%s" must not start with "$".',
                $column,
            ));
        }

        $paths = explode('.*.', $column);

        if (count($paths) !== 2) {
            return new self($function, $column);
        }

        return new self($function, implode('.', $paths), $paths[0]);
    }

    /**
     * Supports the Filament bridge.
     *
     * @return list<array<string, mixed>>
     */
    public function stagesOverRelated(): array
    {
        return match ($this->function) {
            'count' => [...$this->presentColumnFilter(), ['$count' => 'value']],
            'exists' => [['$limit' => 1], ['$count' => 'value']],
            default => [...$this->unwindStage(), ['$group' => ['_id' => null, 'value' => ['$' . $this->function => '$' . $this->column]]]],
        };
    }

    /**
     * Supports the Filament bridge.
     *
     * @return array<string, mixed>
     */
    public function valueIn(string $alias): array
    {
        $value = ['$arrayElemAt' => ['$' . $alias . '.value', 0]];

        return ['$ifNull' => [$value, $this->emptyValue()]];
    }

    /** @return list<array<string, mixed>> */
    private function presentColumnFilter(): array
    {
        if ($this->column === '*') {
            return [];
        }

        return [
            ...$this->unwindStage(),
            ['$match' => ['$expr' => ['$ne' => [['$ifNull' => ['$' . $this->column, null]], null]]]],
        ];
    }

    /** @return list<array<string, string>> */
    private function unwindStage(): array
    {
        if ($this->arrayPath === null) {
            return [];
        }

        return [['$unwind' => '$' . $this->arrayPath]];
    }

    private function emptyValue(): ?int
    {
        return match ($this->function) {
            'count', 'exists' => 0,
            default => null,
        };
    }
}
