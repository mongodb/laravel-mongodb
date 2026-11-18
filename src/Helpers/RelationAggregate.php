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

        if (count($paths) === 1) {
            return new self($function, $column);
        }

        if (count($paths) > 2 || in_array('', $paths, true)) {
            throw new InvalidArgumentException(sprintf(
                'The aggregate column "%s" is not supported. A column can aggregate a field of one array of subdocuments, written as "array.*.field".',
                $column,
            ));
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

    /** Supports the Filament bridge. */
    public function isComputableInParent(): bool
    {
        return $this->arrayPath === null;
    }

    /**
     * Supports the Filament bridge.
     *
     * @return array<string, mixed>
     */
    public function valueInParent(string $documentsPath): array
    {
        return [
            '$let' => [
                'vars' => ['documents' => self::documentsAt($documentsPath)],
                'in' => $this->valueOverDocuments(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function documentsAt(string $path): array
    {
        $single = ['$cond' => [['$eq' => [['$type' => $path], 'object']], [$path], []]];

        return ['$cond' => [['$isArray' => $path], $path, $single]];
    }

    /** @return array<string, mixed> */
    private function valueOverDocuments(): array
    {
        return match ($this->function) {
            'count' => ['$size' => $this->presentDocuments()],
            'exists' => ['$cond' => [['$gt' => [['$size' => '$$documents'], 0]], 1, 0]],
            default => [
                '$cond' => [
                    ['$eq' => [['$size' => '$$documents'], 0]],
                    null,
                    ['$' . $this->function => '$$documents.' . $this->column],
                ],
            ],
        };
    }

    /** @return array<string, mixed>|string */
    private function presentDocuments(): array|string
    {
        if ($this->column === '*') {
            return '$$documents';
        }

        return [
            '$filter' => [
                'input' => '$$documents',
                'as' => 'document',
                'cond' => ['$ne' => [['$ifNull' => ['$$document.' . $this->column, null]], null]],
            ],
        ];
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
