<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Query;

use DateInvalidTimeZoneException;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\Query\Grammars\Grammar as BaseGrammar;
use Illuminate\Support\Facades\Date;
use InvalidArgumentException;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Laravel\Connection;
use stdClass;

use function array_key_exists;
use function date_default_timezone_get;
use function get_object_vars;
use function is_array;
use function is_object;
use function is_string;
use function property_exists;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function substr;

/** @property Connection $connection */
class Grammar extends BaseGrammar
{
    /**
     * Prepare fields for the MongoDB query by aliasing "id" to "_id" and handling arrow notation.
     * Users can override this method to customize field aliasing behavior.
     *
     * @param array<string, mixed> $values The values to prepare
     * @param bool                 $root   Whether this is the root level (affects embedded id field handling)
     * @psalm-param T $values
     *
     * @return array<string, mixed> The prepared values
     * @psalm-return T
     *
     * @template T of array
     */
    public function prepareFieldsForQuery(array $values, bool $root = true): array
    {
        if (array_key_exists('id', $values) && ($root || $this->connection->getRenameEmbeddedIdField())) {
            if (array_key_exists('_id', $values) && $values['id'] !== $values['_id']) {
                throw new InvalidArgumentException('Cannot have both "id" and "_id" fields.');
            }

            $values['_id'] = $values['id'];
            unset($values['id']);
        }

        foreach ($values as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            // "->" arrow notation for subfields is an alias for "." dot notation
            if (str_contains($key, '->')) {
                $newKey = str_replace('->', '.', $key);
                if (array_key_exists($newKey, $values) && $value !== $values[$newKey]) {
                    throw new InvalidArgumentException(sprintf('Cannot have both "%s" and "%s" fields.', $key, $newKey));
                }

                $values[$newKey] = $value;
                unset($values[$key]);
                $key = $newKey;
            }

            // ".id" subfield are alias for "._id"
            if (str_ends_with($key, '.id') && $this->connection->getRenameEmbeddedIdField()) {
                $newKey = substr($key, 0, -3) . '._id';
                if (array_key_exists($newKey, $values) && $value !== $values[$newKey]) {
                    throw new InvalidArgumentException(sprintf('Cannot have both "%s" and "%s" fields.', $key, $newKey));
                }

                $values[$newKey] = $value;
                unset($values[$key]);
            }
        }

        foreach ($values as &$value) {
            if (is_array($value)) {
                $value = $this->prepareFieldsForQuery($value, false);
            } elseif ($value instanceof DateTimeInterface) {
                $value = new UTCDateTime($value);
            }
        }

        return $values;
    }

    /**
     * Prepare fields from the MongoDB result by aliasing "_id" to "id".
     * Users can override this method to customize field aliasing behavior.
     *
     * @param array<string, mixed>|object $values The values to prepare
     * @param bool                        $root   Whether this is the root level (affects embedded id field handling)
     * @psalm-param T                     $values
     *
     * @return array<string, mixed>|object The prepared values
     * @psalm-return T
     *
     * @throws DateInvalidTimeZoneException
     *
     * @template T of array|object
     */
    public function prepareFieldsForResult(array|object $values, bool $root = true): array|object
    {
        if (is_array($values)) {
            if (
                array_key_exists('_id', $values) && ! array_key_exists('id', $values)
                && ($root || $this->connection->getRenameEmbeddedIdField())
            ) {
                $values['id'] = $values['_id'];
                unset($values['_id']);
            }

            foreach ($values as $key => $value) {
                if ($value instanceof UTCDateTime) {
                    $values[$key] = Date::instance($value->toDateTime())
                        ->setTimezone(new DateTimeZone(date_default_timezone_get()));
                } elseif (is_array($value) || is_object($value)) {
                    $values[$key] = $this->prepareFieldsForResult($value, false);
                }
            }
        }

        if ($values instanceof stdClass) {
            if (
                property_exists($values, '_id') && ! property_exists($values, 'id')
                && ($root || $this->connection->getRenameEmbeddedIdField())
            ) {
                $values->id = $values->_id;
                unset($values->_id);
            }

            foreach (get_object_vars($values) as $key => $value) {
                if ($value instanceof UTCDateTime) {
                    $values->{$key} = Date::instance($value->toDateTime())
                        ->setTimezone(new DateTimeZone(date_default_timezone_get()));
                } elseif (is_array($value) || is_object($value)) {
                    $values->{$key} = $this->prepareFieldsForResult($value, false);
                }
            }
        }

        return $values;
    }
}
