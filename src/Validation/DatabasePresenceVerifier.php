<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Validation;

use MongoDB\BSON\Regex;

use function array_map;
use function implode;
use function preg_quote;

class DatabasePresenceVerifier extends \Illuminate\Validation\DatabasePresenceVerifier
{
    /**
     * Count the number of objects in a collection having the given value.
     *
     * @param string $collection
     * @param string $column
     * @param string $value
     * @param int    $excludeId
     * @param string $idColumn
     *
     * @return int
     */
    public function getCount($collection, $column, $value, $excludeId = null, $idColumn = null, array $extra = [])
    {
        $query = $this->table($collection)->where($column, new Regex('^' . preg_quote($value) . '$', '/i'));

        if ($excludeId !== null && $excludeId !== 'NULL') {
            $query->where($idColumn ?: 'id', '<>', $excludeId);
        }

        foreach ($extra as $key => $extraValue) {
            $this->addWhere($query, $key, $extraValue);
        }

        return $query->count();
    }

    /**
     * Count the number of objects in a collection with the given values.
     *
     * @param string $collection
     * @param string $column
     * @param array  $values
     * @param array  $extra
     *
     * @return int
     */
    public function getMultiCount($collection, $column, array $values, array $extra = [])
    {
        // Nothing can match an empty array. Return early to avoid matching an empty string.
        if ($values === []) {
            return 0;
        }

        // Generates a regex like '/^(a|b|c)$/i' which can query multiple values
        $regex = new Regex('^(' . implode('|', array_map(preg_quote(...), $values)) . ')$', 'i');

        $query = $this->table($collection)->where($column, 'regex', $regex);

        foreach ($extra as $key => $extraValue) {
            $this->addWhere($query, $key, $extraValue);
        }

        return $query->count();
    }
}
