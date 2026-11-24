<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Query;

use function is_string;
use function str_ends_with;

/** @internal */
final class ColumnSelection
{
    private readonly string $wildcardOfTheCollection;

    /** @param array<array-key, mixed> $columns */
    public function __construct(
        private readonly array $columns,
        string $collection,
    ) {
        $this->wildcardOfTheCollection = $collection . '.*';
    }

    public function selectsEveryField(): bool
    {
        return $this->fields() === [];
    }

    /** @return list<mixed> */
    public function fields(): array
    {
        $fields = [];

        foreach ($this->columns as $column) {
            if ($this->isWildcardOfTheCollection($column)) {
                return [];
            }

            // A wildcard on another table is dropped, although SQL rejects a table that is not in the query.
            // Throwing would be a BC break: a selection such as ['name', 'clients.*'] returns the name
            // today, and Filament selects the pivot table of a BelongsToMany this way.
            if (! self::isWildcard($column)) {
                $fields[] = $column;
            }
        }

        return $fields;
    }

    private function isWildcardOfTheCollection(mixed $column): bool
    {
        return $column === '*' || $column === $this->wildcardOfTheCollection;
    }

    private static function isWildcard(mixed $column): bool
    {
        return is_string($column) && str_ends_with($column, '.*');
    }
}
