<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Query;

use LogicException;
use stdClass;

use function array_is_list;
use function count;
use function is_array;
use function is_int;
use function sprintf;
use function str_contains;

/** @internal */
final class Projection
{
    /**
     * @param array<string, mixed>|stdClass $projection
     *
     * @return list<array<string, mixed>>
     */
    public static function stages(array|stdClass $projection): array
    {
        $projection = (array) $projection;

        if ($projection === []) {
            return [];
        }

        [$modifications, $exclusions, $selections] = self::partition($projection);

        if ($exclusions !== []) {
            [$idInclusions, $selections] = self::splitPlainIdInclusions($selections);
            $exclusions = [...$idInclusions, ...$exclusions];
        }

        if ($selections !== [] || $modifications === []) {
            return [['$project' => self::translate($projection)]];
        }

        return self::modificationStages($modifications, $exclusions);
    }

    /**
     * @param array<string, mixed> $projection
     *
     * @return array<string, mixed>
     */
    public static function translate(array $projection): array
    {
        $translated = [];

        foreach ($projection as $field => $specification) {
            $translated[$field] = self::translateField($field, self::specification($specification));
        }

        return $translated;
    }

    /**
     * @param array<string, mixed> $modifications
     * @param array<string, mixed> $exclusions
     *
     * @return list<array<string, mixed>>
     */
    private static function modificationStages(array $modifications, array $exclusions): array
    {
        $stages = [['$set' => self::translate($modifications)]];

        if ($exclusions === []) {
            return $stages;
        }

        return [...$stages, ['$project' => $exclusions]];
    }

    /**
     * @param array<string, mixed> $projection
     *
     * @return array{array<string, mixed>, array<string, mixed>, array<string, mixed>}
     */
    private static function partition(array $projection): array
    {
        $modifications = [];
        $exclusions = [];
        $selections = [];

        foreach ($projection as $field => $specification) {
            $specification = self::specification($specification);

            if (self::modifiesField($specification)) {
                $modifications[$field] = $specification;
                continue;
            }

            if (self::excludesField($specification)) {
                $exclusions[$field] = $specification;
                continue;
            }

            $selections[$field] = $specification;
        }

        return [$modifications, $exclusions, $selections];
    }

    private static function modifiesField(mixed $specification): bool
    {
        return is_array($specification) && isset($specification['$slice']);
    }

    private static function specification(mixed $specification): mixed
    {
        return $specification instanceof stdClass ? (array) $specification : $specification;
    }

    private static function excludesField(mixed $specification): bool
    {
        return ! is_array($specification) && ! $specification;
    }

    /**
     * @param array<string, mixed> $selections
     *
     * @return array{array<string, mixed>, array<string, mixed>}
     */
    private static function splitPlainIdInclusions(array $selections): array
    {
        $idInclusions = [];
        $others = [];

        foreach ($selections as $field => $specification) {
            if ($field === '_id' && ($specification === 1 || $specification === true)) {
                $idInclusions[$field] = $specification;
                continue;
            }

            $others[$field] = $specification;
        }

        return [$idInclusions, $others];
    }

    private static function translateField(string $field, mixed $specification): mixed
    {
        if (! is_array($specification)) {
            return $specification;
        }

        if (isset($specification['$slice'])) {
            return self::sliceExpression($field, $specification['$slice']);
        }

        if (isset($specification['$elemMatch'])) {
            throw new LogicException(sprintf(
                'The projection of "%s" uses "$elemMatch", a find-only operator that cannot be compiled into an aggregation $project stage. Project the field with a $filter expression instead.',
                $field,
            ));
        }

        return $specification;
    }

    /** @return array<string, mixed> */
    private static function sliceExpression(string $field, mixed $arguments): array
    {
        if (str_contains($field, '.')) {
            throw new LogicException(sprintf(
                'The projection of "%s" slices a field reached through a path. A find applies that slice per array element along the path, which an aggregation stage cannot express; project the field with your own aggregation expression instead.',
                $field,
            ));
        }

        $path = '$' . $field;

        return [
            '$cond' => [
                ['$isArray' => [$path]],
                ['$slice' => [$path, ...self::sliceCounts($field, $arguments)]],
                $path,
            ],
        ];
    }

    /** @return list<mixed> */
    private static function sliceCounts(string $field, mixed $arguments): array
    {
        if (is_int($arguments)) {
            return [$arguments];
        }

        if (is_array($arguments) && array_is_list($arguments) && count($arguments) === 2) {
            return $arguments;
        }

        throw new LogicException(sprintf(
            'The projection of "%s" slices with arguments that cannot be compiled into an aggregation $project stage. Slice with a count, or with a skip and a count.',
            $field,
        ));
    }
}
