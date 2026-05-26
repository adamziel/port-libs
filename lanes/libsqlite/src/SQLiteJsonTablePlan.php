<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonTablePlan
{
    /**
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @return array{function:string,runnable:bool,arguments:list<mixed>,json:mixed,root:string,used:list<array<string,mixed>>,residual:list<array<string,mixed>>,estimatedCost:int,estimatedRows:int}
     */
    public static function plan(string $function, array $constraints): array
    {
        $function = self::normalizeFunction($function);
        $json = null;
        $root = '$';
        $hasJson = false;
        $used = [];
        $residual = [];

        foreach ($constraints as $constraint) {
            $column = strtolower($constraint['column']);
            $operator = strtoupper($constraint['operator']);
            $usable = $constraint['usable'] ?? true;
            if (!$usable || $operator !== '=') {
                $residual[] = $constraint;
                continue;
            }

            if ($column === 'json' && !$hasJson) {
                self::assertJsonValue($constraint['value']);
                $json = $constraint['value'];
                $hasJson = true;
                $used[] = $constraint + ['argvIndex' => 1, 'omit' => true];
                continue;
            }

            if ($column === 'root') {
                if (!is_string($constraint['value'])) {
                    throw new \InvalidArgumentException('SQLite JSON table root constraint must be text');
                }
                if (!SQLiteJsonPath::isWellFormed($constraint['value'])) {
                    throw new \InvalidArgumentException('SQLite JSON table root constraint is not a well-formed path');
                }
                $root = $constraint['value'];
                $used[] = $constraint + ['argvIndex' => 2, 'omit' => true];
                continue;
            }

            $residual[] = $constraint;
        }

        return [
            'function' => $function,
            'runnable' => $hasJson,
            'arguments' => $hasJson ? [$json, $root] : [],
            'json' => $json,
            'root' => $root,
            'used' => $used,
            'residual' => $residual,
            'estimatedCost' => $hasJson ? ($root === '$' ? 100 : 20) : 1000000,
            'estimatedRows' => $hasJson ? ($root === '$' ? 100 : 10) : 0,
        ];
    }

    /**
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @return list<array<string,mixed>>
     */
    public static function rows(string $function, array $constraints): array
    {
        $plan = self::plan($function, $constraints);
        if (!$plan['runnable']) {
            return [];
        }

        return $plan['function'] === 'json_each'
            ? SQLiteJsonEach::jsonEachSqlFunctionArguments('json_each', $plan['arguments'])
            : SQLiteJsonTree::jsonTreeSqlFunctionArguments('json_tree', $plan['arguments']);
    }

    /**
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @return list<array<string,mixed>>
     */
    public static function filteredRows(string $function, array $constraints): array
    {
        $plan = self::plan($function, $constraints);
        if (!$plan['runnable']) {
            return [];
        }

        $rows = $plan['function'] === 'json_each'
            ? SQLiteJsonEach::jsonEachSqlFunctionArguments('json_each', $plan['arguments'])
            : SQLiteJsonTree::jsonTreeSqlFunctionArguments('json_tree', $plan['arguments']);

        if ($plan['residual'] === []) {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => self::rowMatchesResidualConstraints($row, $plan['residual']),
        ));
    }

    /**
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return list<array<string,mixed>>
     */
    public static function orderedRows(string $function, array $constraints, array $orderBy, ?int $limit = null, int $offset = 0): array
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite JSON table ORDER BY limit must be non-negative');
        }
        if ($offset < 0) {
            throw new \InvalidArgumentException('SQLite JSON table ORDER BY offset must be non-negative');
        }

        $rows = self::filteredRows($function, $constraints);
        if ($orderBy !== []) {
            usort($rows, static fn (array $left, array $right): int => self::compareRowsForOrderBy($left, $right, $orderBy));
        }

        if ($offset > 0 || $limit !== null) {
            return array_slice($rows, $offset, $limit);
        }

        return $rows;
    }

    private static function normalizeFunction(string $function): string
    {
        if (strcasecmp($function, 'json_each') === 0) {
            return 'json_each';
        }
        if (strcasecmp($function, 'json_tree') === 0) {
            return 'json_tree';
        }

        throw new \InvalidArgumentException('SQLite JSON table plan function must be json_each or json_tree');
    }

    private static function assertJsonValue(mixed $value): void
    {
        if ($value instanceof SQLiteBlobValue || $value === null || is_string($value)) {
            return;
        }

        throw new \InvalidArgumentException('SQLite JSON table json constraint must be text, BLOB, or NULL');
    }

    /**
     * @param array<string,mixed> $row
     * @param list<array<string,mixed>> $constraints
     */
    private static function rowMatchesResidualConstraints(array $row, array $constraints): bool
    {
        foreach ($constraints as $constraint) {
            $column = strtolower((string) $constraint['column']);
            if (!array_key_exists($column, $row)) {
                throw new \InvalidArgumentException("SQLite JSON table residual column {$column} is not available");
            }

            if (!self::compareResidualValue($row[$column], strtoupper((string) $constraint['operator']), $constraint['value'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private static function compareResidualValue(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            '=' => self::valuesAreEqual($actual, $expected),
            'IS' => self::valuesAreNotDistinct($actual, $expected),
            'IS NULL' => $actual === null,
            '!=', '<>' => !self::valuesAreEqual($actual, $expected),
            'IS NOT' => !self::valuesAreNotDistinct($actual, $expected),
            'IS NOT NULL' => $actual !== null,
            'IS DISTINCT FROM' => !self::valuesAreNotDistinct($actual, $expected),
            'IS NOT DISTINCT FROM' => self::valuesAreNotDistinct($actual, $expected),
            'LIKE' => self::compareResidualLike($actual, $expected),
            'NOT LIKE' => !self::compareResidualLike($actual, $expected),
            'GLOB' => self::compareResidualGlob($actual, $expected),
            'NOT GLOB' => !self::compareResidualGlob($actual, $expected),
            'REGEXP' => self::compareResidualRegexp($actual, $expected),
            'NOT REGEXP' => !self::compareResidualRegexp($actual, $expected),
            'MATCH' => self::compareResidualMatch($actual, $expected),
            'NOT MATCH' => !self::compareResidualMatch($actual, $expected),
            'IN' => self::compareResidualIn($actual, $expected),
            'NOT IN' => self::compareResidualNotIn($actual, $expected),
            'BETWEEN' => self::compareResidualBetween($actual, $expected),
            'NOT BETWEEN' => self::compareResidualNotBetween($actual, $expected),
            '<', '<=', '>', '>=' => self::compareResidualOrderedPredicate($actual, $operator, $expected),
            default => throw new \InvalidArgumentException("SQLite JSON table residual operator {$operator} is not supported"),
        };
    }

    private static function compareResidualBetween(mixed $actual, mixed $expected): bool
    {
        if (!is_array($expected) || count($expected) !== 2) {
            throw new \InvalidArgumentException('SQLite JSON table residual operator BETWEEN expects a two-value list');
        }

        [$lower, $upper] = array_values($expected);
        if ($actual === null || $lower === null || $upper === null) {
            return false;
        }

        return self::compareResidualOrdered($actual, $lower) >= 0
            && self::compareResidualOrdered($actual, $upper) <= 0;
    }

    private static function compareResidualNotBetween(mixed $actual, mixed $expected): bool
    {
        if (!is_array($expected) || count($expected) !== 2) {
            throw new \InvalidArgumentException('SQLite JSON table residual operator BETWEEN expects a two-value list');
        }

        [$lower, $upper] = array_values($expected);
        if ($actual === null || $lower === null || $upper === null) {
            return false;
        }

        return self::compareResidualOrdered($actual, $lower) < 0
            || self::compareResidualOrdered($actual, $upper) > 0;
    }


    private static function compareResidualIn(mixed $actual, mixed $expected): bool
    {
        if (!is_array($expected)) {
            throw new \InvalidArgumentException('SQLite JSON table residual operator IN expects a list value');
        }
        if ($actual === null) {
            return false;
        }

        foreach ($expected as $value) {
            if ($value !== null && self::valuesAreEqual($actual, $value)) {
                return true;
            }
        }

        return false;
    }

    private static function compareResidualNotIn(mixed $actual, mixed $expected): bool
    {
        if (!is_array($expected)) {
            throw new \InvalidArgumentException('SQLite JSON table residual operator NOT IN expects a list value');
        }
        if ($actual === null || in_array(null, $expected, true)) {
            return false;
        }

        return !self::compareResidualIn($actual, $expected);
    }

    private static function compareResidualLike(mixed $actual, mixed $expected): bool
    {
        if ($actual === null || $expected === null) {
            return false;
        }
        if (!is_string($actual) || !is_string($expected)) {
            throw new \InvalidArgumentException('SQLite JSON table residual operator LIKE expects text values');
        }

        return SQLiteDatabase::likeMatches($actual, $expected);
    }

    private static function compareResidualGlob(mixed $actual, mixed $expected): bool
    {
        if ($actual === null || $expected === null) {
            return false;
        }
        if (!is_string($actual) || !is_string($expected)) {
            throw new \InvalidArgumentException('SQLite JSON table residual operator GLOB expects text values');
        }

        return SQLiteDatabase::globMatches($actual, $expected);
    }

    private static function compareResidualRegexp(mixed $actual, mixed $expected): bool
    {
        if ($actual === null || $expected === null) {
            return false;
        }
        if (!is_string($actual)) {
            throw new \InvalidArgumentException('SQLite JSON table residual operator REGEXP expects text row values');
        }
        if (!is_array($expected) || !array_key_exists('pattern', $expected) || !array_key_exists('regexp', $expected)) {
            throw new \InvalidArgumentException('SQLite JSON table residual operator REGEXP expects a pattern and callback payload');
        }
        if (!is_string($expected['pattern']) || !is_callable($expected['regexp'])) {
            throw new \InvalidArgumentException('SQLite JSON table residual operator REGEXP expects a text pattern and callable callback');
        }

        return SQLiteDatabase::regexpMatches($actual, $expected['pattern'], $expected['regexp']);
    }

    private static function compareResidualMatch(mixed $actual, mixed $expected): bool
    {
        if ($actual === null || $expected === null) {
            return false;
        }
        if (!is_string($actual)) {
            throw new \InvalidArgumentException('SQLite JSON table residual operator MATCH expects text row values');
        }
        if (!is_array($expected) || !array_key_exists('pattern', $expected) || !array_key_exists('match', $expected)) {
            throw new \InvalidArgumentException('SQLite JSON table residual operator MATCH expects a pattern and callback payload');
        }
        if (!is_string($expected['pattern']) || !is_callable($expected['match'])) {
            throw new \InvalidArgumentException('SQLite JSON table residual operator MATCH expects a text pattern and callable callback');
        }

        $matched = $expected['match']($expected['pattern'], $actual);
        if (!is_bool($matched)) {
            throw new \InvalidArgumentException('SQLite JSON table residual operator MATCH callback must return bool');
        }

        return $matched;
    }

    private static function compareResidualOrderedPredicate(mixed $actual, string $operator, mixed $expected): bool
    {
        if ($actual === null || $expected === null) {
            return false;
        }

        $comparison = self::compareResidualOrdered($actual, $expected);

        return match ($operator) {
            '<' => $comparison < 0,
            '<=' => $comparison <= 0,
            '>' => $comparison > 0,
            '>=' => $comparison >= 0,
            default => throw new \InvalidArgumentException("SQLite JSON table residual operator {$operator} is not supported"),
        };
    }

    private static function compareResidualOrdered(mixed $actual, mixed $expected): int
    {
        $actualClass = self::sqliteSortClass($actual);
        $expectedClass = self::sqliteSortClass($expected);
        if ($actualClass !== $expectedClass) {
            return $actualClass <=> $expectedClass;
        }

        if ($actualClass === 1) {
            return ((float) $actual) <=> ((float) $expected);
        }

        if ($actualClass === 2) {
            return strcmp((string) $actual, (string) $expected);
        }

        throw new \InvalidArgumentException('SQLite JSON table residual ordered comparison supports only NULL, numeric, and text values');
    }

    /**
     * @param list<array{column:string,direction?:string}> $orderBy
     */
    private static function compareRowsForOrderBy(array $left, array $right, array $orderBy): int
    {
        foreach ($orderBy as $term) {
            $column = strtolower($term['column']);
            if (!array_key_exists($column, $left) || !array_key_exists($column, $right)) {
                throw new \InvalidArgumentException("SQLite JSON table ORDER BY column {$column} is not available");
            }

            $direction = strtoupper($term['direction'] ?? 'ASC');
            if ($direction !== 'ASC' && $direction !== 'DESC') {
                throw new \InvalidArgumentException('SQLite JSON table ORDER BY direction must be ASC or DESC');
            }

            $comparison = self::compareResidualOrdered($left[$column], $right[$column]);
            if ($comparison !== 0) {
                return $direction === 'DESC' ? -$comparison : $comparison;
            }
        }

        return 0;
    }

    private static function sqliteSortClass(mixed $value): int
    {
        if ($value === null) {
            return 0;
        }
        if (is_int($value) || is_float($value)) {
            return 1;
        }
        if (is_string($value)) {
            return 2;
        }

        throw new \InvalidArgumentException('SQLite JSON table residual ordered comparison supports only NULL, numeric, and text values');
    }

    private static function valuesAreNotDistinct(mixed $left, mixed $right): bool
    {
        if ($left === null || $right === null) {
            return $left === null && $right === null;
        }

        return self::valuesAreEqual($left, $right);
    }

    private static function valuesAreEqual(mixed $left, mixed $right): bool
    {
        if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
            return (float) $left === (float) $right;
        }

        return $left === $right;
    }
}
