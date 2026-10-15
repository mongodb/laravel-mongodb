<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Helpers;

use InvalidArgumentException;

use function implode;
use function in_array;
use function sprintf;
use function strtolower;

/** @internal */
final readonly class RelationAggregate
{
    private const FUNCTIONS = ['avg', 'count', 'exists', 'max', 'min', 'sum'];

    private function __construct(
        public string $function,
        public string $column,
    ) {
    }

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

        return new self($function, RelationLookup::field($column));
    }

    /** @return array<string, mixed> */
    public function expressionOver(string $alias): array
    {
        $documents = '$' . $alias;

        return match ($this->function) {
            'count' => ['$size' => $documents],
            'exists' => ['$gt' => [['$size' => $documents], 0]],
            default => ['$' . $this->function => $documents . '.' . $this->column],
        };
    }
}
