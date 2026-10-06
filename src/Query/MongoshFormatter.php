<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Query;

use Exception;
use MongoDB\BSON\Binary;
use MongoDB\BSON\Decimal128;
use MongoDB\BSON\Document;
use MongoDB\BSON\Int64;
use MongoDB\BSON\Javascript;
use MongoDB\BSON\MaxKey;
use MongoDB\BSON\MinKey;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\PackedArray;
use MongoDB\BSON\Regex;
use MongoDB\BSON\Timestamp;
use MongoDB\BSON\Undefined;
use MongoDB\BSON\UTCDateTime;
use stdClass;
use UnexpectedValueException;

use function array_is_list;
use function array_key_exists;
use function array_key_first;
use function base64_encode;
use function bin2hex;
use function count;
use function get_object_vars;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_infinite;
use function is_int;
use function is_nan;
use function is_string;
use function json_encode;
use function ltrim;
use function preg_match;
use function sprintf;
use function str_starts_with;
use function strcmp;
use function strlen;
use function strtr;
use function substr;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Renders a MongoDB command as a mongosh statement for the Laravel query log.
 *
 * Telescope and Debugbar print that string as the query. Collection helpers
 * stay readable and can be pasted into mongosh. Commands that do not map onto
 * a helper are logged as `db.runCommand(EJSON.parse(...))`, which mongosh runs
 * without a conversion step.
 *
 * @internal
 */
final class MongoshFormatter
{
    private const MAX_DEPTH = 100;

    /** Largest integer JavaScript can store exactly. */
    private const JS_MAX_SAFE_INTEGER = '9007199254740991';

    /** @param array<string, mixed> $command */
    public static function format(array $command): string
    {
        try {
            return self::formatCommand($command);
        } catch (UnexpectedValueException) {
            return self::runCommand($command);
        }
    }

    /** @param array<string, mixed> $command */
    private static function formatCommand(array $command): string
    {
        return match (true) {
            // findAndModify includes an "update" field, so it is matched before the update command.
            array_key_exists('findAndModify', $command) => self::formatFindAndModify($command),
            array_key_exists('find', $command) => self::formatFind($command),
            array_key_exists('insert', $command) => self::formatInsert($command),
            array_key_exists('update', $command) => self::formatUpdate($command),
            array_key_exists('delete', $command) => self::formatDelete($command),
            array_key_exists('aggregate', $command) => self::formatAggregate($command),
            array_key_exists('distinct', $command) => self::formatDistinct($command),
            array_key_exists('createIndexes', $command) => self::formatCreateIndexes($command),
            array_key_exists('dropIndexes', $command) => self::formatDropIndexes($command),
            array_key_exists('drop', $command) => self::formatDrop($command),
            array_key_exists('count', $command) => self::formatCount($command),
            array_key_exists('listIndexes', $command) => self::formatListIndexes($command),
            default => throw new UnexpectedValueException('Unrecognized command.'),
        };
    }

    /** @param array<string, mixed> $command */
    private static function formatFind(array $command): string
    {
        self::assertOnlyKeys($command, [
            'find',
            'filter',
            'projection',
            'sort',
            'skip',
            'limit',
            'hint',
            'comment',
            'collation',
            'maxTimeMS',
            'batchSize',
            'singleBatch',
        ]);

        $shell = self::collection($command['find']) . '.find(' . self::formatValue($command['filter'] ?? new stdClass());
        if (array_key_exists('projection', $command)) {
            $shell .= ', ' . self::formatValue($command['projection']);
        }

        $shell .= ')';

        // singleBatch is a driver cursor flag added when limit is 1.
        foreach (['sort', 'skip', 'limit', 'collation', 'hint', 'comment', 'maxTimeMS', 'batchSize'] as $method) {
            if (! array_key_exists($method, $command)) {
                continue;
            }

            $shell .= '.' . $method . '(' . self::formatValue($command[$method]) . ')';
        }

        return $shell;
    }

    /** @param array<string, mixed> $command */
    private static function formatInsert(array $command): string
    {
        self::assertOnlyKeys($command, ['insert', 'documents', 'ordered']);
        $documents = self::listItems($command['documents'] ?? null);
        $ordered = self::ordered($command);
        $collection = self::collection($command['insert']);

        if (count($documents) === 1 && $ordered) {
            return self::call($collection, 'insertOne', [$documents[0]]);
        }

        $arguments = [$documents];
        if (! $ordered) {
            $arguments[] = ['ordered' => false];
        }

        return self::call($collection, 'insertMany', $arguments);
    }

    /** @param array<string, mixed> $command */
    private static function formatUpdate(array $command): string
    {
        self::assertOnlyKeys($command, ['update', 'updates', 'ordered']);
        $updates = self::listItems($command['updates'] ?? null);
        if ($updates === []) {
            throw new UnexpectedValueException('Update command has no updates.');
        }

        $ordered = self::ordered($command);
        $collection = self::collection($command['update']);
        if (count($updates) === 1 && $ordered) {
            return self::formatUpdateStatement($collection, self::asDocument($updates[0]));
        }

        $operations = [];
        foreach ($updates as $update) {
            $operations[] = self::bulkUpdate(self::asDocument($update));
        }

        $arguments = [$operations];
        if (! $ordered) {
            $arguments[] = ['ordered' => false];
        }

        return self::call($collection, 'bulkWrite', $arguments);
    }

    /** @param array<string, mixed> $command */
    private static function formatDelete(array $command): string
    {
        self::assertOnlyKeys($command, ['delete', 'deletes', 'ordered']);
        $deletes = self::listItems($command['deletes'] ?? null);
        if ($deletes === []) {
            throw new UnexpectedValueException('Delete command has no deletes.');
        }

        $ordered = self::ordered($command);
        $collection = self::collection($command['delete']);
        if (count($deletes) === 1 && $ordered) {
            return self::formatDeleteStatement($collection, self::asDocument($deletes[0]));
        }

        $operations = [];
        foreach ($deletes as $delete) {
            $operations[] = self::bulkDelete(self::asDocument($delete));
        }

        $arguments = [$operations];
        if (! $ordered) {
            $arguments[] = ['ordered' => false];
        }

        return self::call($collection, 'bulkWrite', $arguments);
    }

    /** @param array<string, mixed> $command */
    private static function formatAggregate(array $command): string
    {
        self::assertOnlyKeys($command, [
            'aggregate',
            'pipeline',
            'cursor',
            'allowDiskUse',
            'collation',
            'hint',
            'comment',
            'let',
            'maxTimeMS',
        ]);

        if (isset($command['cursor']) && ! self::isEmptyDocument($command['cursor'])) {
            throw new UnexpectedValueException('Aggregate cursor is not empty.');
        }

        $count = self::countDocuments($command);
        if ($count !== null) {
            return $count;
        }

        $arguments = [$command['pipeline'] ?? []];
        $options = self::pick($command, ['allowDiskUse', 'collation', 'hint', 'comment', 'let', 'maxTimeMS']);
        if ($options !== []) {
            $arguments[] = $options;
        }

        return self::call(self::collection($command['aggregate']), 'aggregate', $arguments);
    }

    /**
     * Recognise the aggregate pipeline that countDocuments() sends.
     *
     * @param array<string, mixed> $command
     */
    private static function countDocuments(array $command): ?string
    {
        if (self::pick($command, ['allowDiskUse', 'let']) !== []) {
            return null;
        }

        $stages = self::listItems($command['pipeline'] ?? null);
        if ($stages === []) {
            return null;
        }

        $match = self::asDocument($stages[0]);
        if (! isset($match['$match']) || count($match) !== 1) {
            return null;
        }

        $index = 1;
        $options = self::pick($command, ['collation', 'hint', 'comment', 'maxTimeMS']);
        if (isset($stages[$index]) && self::isSingleStage($stages[$index], '$skip')) {
            $options['skip'] = self::asDocument($stages[$index])['$skip'];
            $index++;
        }

        if (isset($stages[$index]) && self::isSingleStage($stages[$index], '$limit')) {
            $options['limit'] = self::asDocument($stages[$index])['$limit'];
            $index++;
        }

        if (! isset($stages[$index]) || isset($stages[$index + 1]) || ! self::isCountGroup($stages[$index])) {
            return null;
        }

        $arguments = [$match['$match']];
        if ($options !== []) {
            $arguments[] = $options;
        }

        return self::call(self::collection($command['aggregate']), 'countDocuments', $arguments);
    }

    private static function isSingleStage(mixed $stage, string $name): bool
    {
        $document = self::asDocument($stage);

        return count($document) === 1 && array_key_exists($name, $document);
    }

    private static function isCountGroup(mixed $stage): bool
    {
        $group = self::asDocument($stage);
        if (count($group) !== 1 || ! isset($group['$group'])) {
            return false;
        }

        $spec = self::asDocument($group['$group']);
        if (count($spec) !== 2 || ($spec['_id'] ?? null) !== 1 || ! isset($spec['n'])) {
            return false;
        }

        $sum = self::asDocument($spec['n']);

        return count($sum) === 1 && ($sum['$sum'] ?? null) === 1;
    }

    /** @param array<string, mixed> $command */
    private static function formatDistinct(array $command): string
    {
        self::assertOnlyKeys($command, ['distinct', 'key', 'query', 'collation', 'comment', 'hint', 'maxTimeMS']);
        if (! is_string($command['key'] ?? null)) {
            throw new UnexpectedValueException('Distinct key must be a string.');
        }

        $arguments = [$command['key']];
        $options = self::pick($command, ['collation', 'comment', 'hint', 'maxTimeMS']);
        if (array_key_exists('query', $command) || $options !== []) {
            $arguments[] = $command['query'] ?? new stdClass();
        }

        if ($options !== []) {
            $arguments[] = $options;
        }

        return self::call(self::collection($command['distinct']), 'distinct', $arguments);
    }

    /** @param array<string, mixed> $command */
    private static function formatFindAndModify(array $command): string
    {
        self::assertOnlyKeys($command, [
            'findAndModify',
            'query',
            'sort',
            'remove',
            'update',
            'new',
            'fields',
            'upsert',
            'collation',
            'arrayFilters',
            'hint',
            'comment',
        ]);

        $collection = self::collection($command['findAndModify']);
        $query = $command['query'] ?? new stdClass();
        $options = self::pick($command, ['sort', 'collation', 'hint', 'comment']);
        if (array_key_exists('fields', $command)) {
            $options['projection'] = $command['fields'];
        }

        if (! empty($command['remove'])) {
            if (array_key_exists('update', $command) || ! empty($command['upsert'])) {
                throw new UnexpectedValueException('findAndModify remove cannot include an update.');
            }

            $arguments = [$query];
            if ($options !== []) {
                $arguments[] = $options;
            }

            return self::call($collection, 'findOneAndDelete', $arguments);
        }

        if (! array_key_exists('update', $command)) {
            throw new UnexpectedValueException('findAndModify is missing an update.');
        }

        if (! empty($command['upsert'])) {
            $options['upsert'] = true;
        }

        if (! empty($command['new'])) {
            $options['returnDocument'] = 'after';
        }

        if (array_key_exists('arrayFilters', $command)) {
            $options['arrayFilters'] = $command['arrayFilters'];
        }

        $method = self::isReplacement($command['update']) ? 'findOneAndReplace' : 'findOneAndUpdate';
        $arguments = [$query, $command['update']];
        if ($options !== []) {
            $arguments[] = $options;
        }

        return self::call($collection, $method, $arguments);
    }

    /** @param array<string, mixed> $command */
    private static function formatCreateIndexes(array $command): string
    {
        self::assertOnlyKeys($command, ['createIndexes', 'indexes']);
        $indexes = self::listItems($command['indexes'] ?? null);
        $collection = self::collection($command['createIndexes']);
        if (count($indexes) !== 1) {
            return self::call($collection, 'createIndexes', [$indexes]);
        }

        $index = self::asDocument($indexes[0]);
        if (! array_key_exists('key', $index)) {
            throw new UnexpectedValueException('Index is missing a key.');
        }

        $key = $index['key'];
        unset($index['key']);
        $arguments = [$key];
        if ($index !== []) {
            $arguments[] = $index;
        }

        return self::call($collection, 'createIndex', $arguments);
    }

    /** @param array<string, mixed> $command */
    private static function formatDropIndexes(array $command): string
    {
        self::assertOnlyKeys($command, ['dropIndexes', 'index']);
        $collection = self::collection($command['dropIndexes']);
        if (($command['index'] ?? null) === '*') {
            return self::call($collection, 'dropIndexes', []);
        }

        return self::call($collection, 'dropIndex', [$command['index'] ?? null]);
    }

    /** @param array<string, mixed> $command */
    private static function formatDrop(array $command): string
    {
        self::assertOnlyKeys($command, ['drop']);

        return self::call(self::collection($command['drop']), 'drop', []);
    }

    /** @param array<string, mixed> $command */
    private static function formatCount(array $command): string
    {
        self::assertOnlyKeys($command, ['count', 'query', 'limit', 'skip', 'hint', 'collation', 'maxTimeMS', 'comment']);
        $collection = self::collection($command['count']);
        $options = self::pick($command, ['limit', 'skip', 'hint', 'collation', 'maxTimeMS', 'comment']);
        if (! array_key_exists('query', $command) && $options === []) {
            return self::call($collection, 'estimatedDocumentCount', []);
        }

        if (! array_key_exists('query', $command)) {
            throw new UnexpectedValueException('count options require a query.');
        }

        $arguments = [$command['query']];
        if ($options !== []) {
            $arguments[] = $options;
        }

        return self::call($collection, 'countDocuments', $arguments);
    }

    /** @param array<string, mixed> $command */
    private static function formatListIndexes(array $command): string
    {
        self::assertOnlyKeys($command, ['listIndexes']);

        return self::call(self::collection($command['listIndexes']), 'getIndexes', []);
    }

    /** @param array<string, mixed> $update */
    private static function formatUpdateStatement(string $collection, array $update): string
    {
        [$method, $query, $change, $options] = self::updateParts($update);
        $arguments = [$query, $change];
        if ($options !== []) {
            $arguments[] = $options;
        }

        return self::call($collection, $method, $arguments);
    }

    /**
     * @param array<string, mixed> $update
     *
     * @return array<string, mixed>
     */
    private static function bulkUpdate(array $update): array
    {
        [$method, $query, $change, $options] = self::updateParts($update);
        $body = ['filter' => $query];
        $body[$method === 'replaceOne' ? 'replacement' : 'update'] = $change;

        return [$method => $body + $options];
    }

    /**
     * @param array<string, mixed> $update
     *
     * @return array{0: string, 1: mixed, 2: mixed, 3: array<string, mixed>}
     */
    private static function updateParts(array $update): array
    {
        self::assertOnlyKeys($update, ['q', 'u', 'multi', 'upsert', 'collation', 'hint', 'arrayFilters', 'sort']);
        $multi = (bool) ($update['multi'] ?? false);
        $change = $update['u'] ?? new stdClass();
        $replacement = self::isReplacement($change);
        if ($replacement && $multi) {
            throw new UnexpectedValueException('A multi update cannot replace documents.');
        }

        if ($replacement && array_key_exists('arrayFilters', $update)) {
            throw new UnexpectedValueException('arrayFilters does not apply to a replacement.');
        }

        if ($multi && array_key_exists('sort', $update)) {
            throw new UnexpectedValueException('sort does not apply to a multi update.');
        }

        $method = $replacement ? 'replaceOne' : ($multi ? 'updateMany' : 'updateOne');
        $options = [];
        if (! empty($update['upsert'])) {
            $options['upsert'] = true;
        }

        foreach (['collation', 'hint', 'arrayFilters', 'sort'] as $key) {
            if (array_key_exists($key, $update)) {
                $options[$key] = $update[$key];
            }
        }

        return [$method, $update['q'] ?? new stdClass(), $change, $options];
    }

    /** @param array<string, mixed> $delete */
    private static function formatDeleteStatement(string $collection, array $delete): string
    {
        [$method, $query, $options] = self::deleteParts($delete);
        $arguments = [$query];
        if ($options !== []) {
            $arguments[] = $options;
        }

        return self::call($collection, $method, $arguments);
    }

    /**
     * @param array<string, mixed> $delete
     *
     * @return array<string, mixed>
     */
    private static function bulkDelete(array $delete): array
    {
        [$method, $query, $options] = self::deleteParts($delete);

        return [$method => ['filter' => $query] + $options];
    }

    /**
     * @param array<string, mixed> $delete
     *
     * @return array{0: string, 1: mixed, 2: array<string, mixed>}
     */
    private static function deleteParts(array $delete): array
    {
        self::assertOnlyKeys($delete, ['q', 'limit', 'collation', 'hint']);
        $limit = $delete['limit'] ?? 0;
        if ($limit !== 0 && $limit !== 1) {
            throw new UnexpectedValueException('Unexpected delete limit.');
        }

        return [
            $limit === 1 ? 'deleteOne' : 'deleteMany',
            $delete['q'] ?? new stdClass(),
            self::pick($delete, ['collation', 'hint']),
        ];
    }

    private static function isReplacement(mixed $update): bool
    {
        if (is_array($update) && array_is_list($update)) {
            return false;
        }

        foreach (self::asDocument($update) as $key => $value) {
            if (is_string($key) && str_starts_with($key, '$')) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $command */
    private static function ordered(array $command): bool
    {
        $ordered = $command['ordered'] ?? true;
        if ($ordered !== true && $ordered !== false) {
            throw new UnexpectedValueException('Invalid ordered flag.');
        }

        return $ordered;
    }

    /** @param array<string, mixed> $command */
    private static function runCommand(array $command): string
    {
        try {
            $json = Document::fromPHP($command)->toCanonicalExtendedJSON();
        } catch (Exception) {
            $name = array_key_first($command);

            return 'db.runCommand(' . self::jsString(is_string($name) ? $name : 'command') . ')';
        }

        return 'db.runCommand(EJSON.parse(' . self::jsSingleQuoted($json) . '))';
    }

    private static function collection(mixed $name): string
    {
        if (! is_string($name)) {
            throw new UnexpectedValueException('Collection name must be a string.');
        }

        return 'db.getCollection(' . self::jsString($name) . ')';
    }

    /** @param list<mixed> $arguments */
    private static function call(string $collection, string $method, array $arguments): string
    {
        $formatted = [];
        foreach ($arguments as $argument) {
            $formatted[] = self::formatValue($argument);
        }

        return $collection . '.' . $method . '(' . implode(', ', $formatted) . ')';
    }

    private static function formatValue(mixed $value, int $depth = 0): string
    {
        if ($depth > self::MAX_DEPTH) {
            throw new UnexpectedValueException('Maximum nesting depth exceeded.');
        }

        if ($value instanceof Document || $value instanceof PackedArray) {
            return self::formatValue($value->toPHP(), $depth + 1);
        }

        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value) => self::formatLong($value),
            is_float($value) => self::formatFloat($value),
            is_string($value) => self::jsString($value),
            is_array($value) => self::formatArray($value, $depth),
            $value instanceof stdClass => self::formatFields(get_object_vars($value), $depth),
            $value instanceof ObjectId => 'ObjectId(' . self::jsString((string) $value) . ')',
            $value instanceof UTCDateTime => 'ISODate(' . self::jsString($value->toDateTime()->format('Y-m-d\TH:i:s.v\Z')) . ')',
            $value instanceof Decimal128 => 'NumberDecimal(' . self::jsString((string) $value) . ')',
            $value instanceof Binary => self::formatBinary($value),
            $value instanceof Regex => 'RegExp(' . self::jsString($value->getPattern()) . ', ' . self::jsString($value->getFlags()) . ')',
            $value instanceof Javascript => self::formatJavascript($value, $depth),
            $value instanceof Timestamp => 'Timestamp(' . self::formatLong($value->getTimestamp()) . ', ' . self::formatLong($value->getIncrement()) . ')',
            $value instanceof MinKey => 'MinKey()',
            $value instanceof MaxKey => 'MaxKey()',
            $value instanceof Int64 => self::formatLong((string) $value),
            $value instanceof Undefined => 'undefined',
            default => throw new UnexpectedValueException('Unsupported BSON value.'),
        };
    }

    /** @param array<mixed> $value */
    private static function formatArray(array $value, int $depth): string
    {
        if (array_is_list($value)) {
            $items = [];
            foreach ($value as $item) {
                $items[] = self::formatValue($item, $depth + 1);
            }

            return '[' . implode(', ', $items) . ']';
        }

        return self::formatFields($value, $depth);
    }

    /** @param array<int|string, mixed> $fields */
    private static function formatFields(array $fields, int $depth): string
    {
        $items = [];
        foreach ($fields as $key => $value) {
            $items[] = self::formatKey((string) $key) . ': ' . self::formatValue($value, $depth + 1);
        }

        return '{' . implode(', ', $items) . '}';
    }

    private static function formatBinary(Binary $binary): string
    {
        if ($binary->getType() === Binary::TYPE_UUID && strlen($binary->getData()) === 16) {
            $hex = bin2hex($binary->getData());

            return 'UUID(' . self::jsString(sprintf(
                '%s-%s-%s-%s-%s',
                substr($hex, 0, 8),
                substr($hex, 8, 4),
                substr($hex, 12, 4),
                substr($hex, 16, 4),
                substr($hex, 20, 12),
            )) . ')';
        }

        return 'BinData(' . $binary->getType() . ', ' . self::jsString(base64_encode($binary->getData())) . ')';
    }

    private static function formatJavascript(Javascript $javascript, int $depth): string
    {
        $code = self::jsString($javascript->getCode());
        $scope = $javascript->getScope();
        if ($scope === null || self::isEmptyDocument($scope)) {
            return 'Code(' . $code . ')';
        }

        return 'Code(' . $code . ', ' . self::formatValue($scope, $depth + 1) . ')';
    }

    private static function formatFloat(float $value): string
    {
        if (is_nan($value)) {
            return 'NaN';
        }

        if (is_infinite($value)) {
            return $value > 0 ? 'Infinity' : '-Infinity';
        }

        $json = json_encode($value, JSON_THROW_ON_ERROR);
        if (! is_string($json)) {
            throw new UnexpectedValueException('Unable to encode a number.');
        }

        return $json;
    }

    private static function formatLong(int|string $value): string
    {
        $string = (string) $value;
        if (preg_match('/^-?(?:0|[1-9]\d*)$/', $string) !== 1) {
            throw new UnexpectedValueException('Invalid integer.');
        }

        $negative = str_starts_with($string, '-');
        $digits = ltrim($negative ? substr($string, 1) : $string, '0');
        if ($digits === '') {
            return '0';
        }

        $unsafe = strlen($digits) > strlen(self::JS_MAX_SAFE_INTEGER)
            || (strlen($digits) === strlen(self::JS_MAX_SAFE_INTEGER) && strcmp($digits, self::JS_MAX_SAFE_INTEGER) > 0);
        if ($unsafe) {
            return 'NumberLong("' . ($negative ? '-' : '') . $digits . '")';
        }

        return ($negative ? '-' : '') . $digits;
    }

    private static function formatKey(string $key): string
    {
        if (preg_match('/^[A-Za-z_$][A-Za-z0-9_$]*$/', $key) === 1) {
            return $key;
        }

        return self::jsString($key);
    }

    private static function jsString(string $value): string
    {
        $json = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($json)) {
            throw new UnexpectedValueException('Unable to encode a string.');
        }

        return $json;
    }

    private static function jsSingleQuoted(string $value): string
    {
        return "'" . strtr($value, [
            '\\' => '\\\\',
            "'" => "\\'",
            "\n" => '\\n',
            "\r" => '\\r',
            "\u{2028}" => '\\u2028',
            "\u{2029}" => '\\u2029',
        ]) . "'";
    }

    /**
     * @param array<mixed> $document
     * @param list<string> $allowed
     */
    private static function assertOnlyKeys(array $document, array $allowed): void
    {
        foreach ($document as $key => $value) {
            if (! in_array((string) $key, $allowed, true)) {
                throw new UnexpectedValueException('Unexpected field "' . $key . '".');
            }
        }
    }

    /**
     * @param array<mixed> $document
     * @param list<string> $keys
     *
     * @return array<string, mixed>
     */
    private static function pick(array $document, array $keys): array
    {
        $picked = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $document)) {
                $picked[$key] = $document[$key];
            }
        }

        return $picked;
    }

    /** @return array<string, mixed> */
    private static function asDocument(mixed $value): array
    {
        if ($value instanceof stdClass) {
            return get_object_vars($value);
        }

        if ($value instanceof Document) {
            $php = $value->toPHP();
            if ($php instanceof stdClass) {
                return get_object_vars($php);
            }

            if (is_array($php)) {
                return $php;
            }
        }

        if (is_array($value) && (! array_is_list($value) || $value === [])) {
            return $value;
        }

        throw new UnexpectedValueException('Expected a document.');
    }

    /** @return list<mixed> */
    private static function listItems(mixed $value): array
    {
        if ($value instanceof PackedArray) {
            $value = $value->toPHP();
        }

        if (is_array($value) && (array_is_list($value) || $value === [])) {
            return $value;
        }

        throw new UnexpectedValueException('Expected a list.');
    }

    private static function isEmptyDocument(mixed $value): bool
    {
        if ($value instanceof stdClass) {
            return get_object_vars($value) === [];
        }

        if ($value instanceof Document) {
            return self::isEmptyDocument($value->toPHP());
        }

        return is_array($value) && $value === [];
    }
}
