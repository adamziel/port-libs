<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSelectProjection
{
    /**
     * @param iterable<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $expressions
     * @return list<array<string,mixed>>
     */
    public static function project(iterable $rows, array $expressions): array
    {
        if ($expressions === []) {
            throw new \InvalidArgumentException('SQLite SELECT projection needs at least one expression');
        }

        $projectedRows = [];
        foreach ($rows as $row) {
            $projected = [];
            $projectedAffinities = [];
            foreach ($expressions as $index => $expression) {
                if (($expression['type'] ?? null) === 'wildcard') {
                    foreach (self::wildcardValues($row, $expression) as $alias => $value) {
                        self::appendProjectedValue($projected, $alias, $value);
                    }
                    continue;
                }

                $alias = self::expressionAlias($expression, $index);
                $evaluationRow = ($expression['hiddenOrderColumn'] ?? false) === true
                    ? array_merge($row, $projected)
                    : $row;
                $projectedAlias = self::appendProjectedValue($projected, $alias, self::projectedValue(self::evaluateExpression($evaluationRow, $expression)));
                $affinity = self::projectedAffinity($row, $expression);
                if ($affinity !== null) {
                    $projectedAffinities[$projectedAlias] = $affinity;
                }
            }
            if ($projectedAffinities !== []) {
                $projected['__sqlite_column_affinities'] = $projectedAffinities;
            }
            $projectedRows[] = $projected;
        }

        return $projectedRows;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $expression
     */
    private static function evaluateExpression(array $row, array $expression): mixed
    {
        $kind = $expression['type'] ?? null;

        if ($kind === 'column' || $kind === 'literal' || $kind === 'collate' || $kind === 'function' || $kind === 'cast' || $kind === 'unary' || $kind === 'binary' || $kind === 'predicate' || $kind === 'subquery' || $kind === 'case') {
            return SQLiteSelectExpression::evaluate($row, $expression);
        }

        return match ($kind) {
            default => throw new \InvalidArgumentException('SQLite SELECT projection expression type must be column, literal, collate, function, cast, unary, binary, predicate, subquery, case, or wildcard'),
        };
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $expression
     * @return array<string,mixed>
     */
    private static function wildcardValues(array $row, array $expression): array
    {
        if (array_key_exists('alias', $expression)) {
            throw new \InvalidArgumentException('SQLite SELECT wildcard projection cannot have an alias');
        }
        if (($expression['aggregateWildcard'] ?? false) === true && isset($expression['columns']) && is_array($expression['columns']) && array_is_list($expression['columns'])) {
            return self::aggregateWildcardValues($row, $expression);
        }

        $prefix = null;
        if (array_key_exists('prefix', $expression)) {
            $prefix = self::requiredString($expression, 'prefix', 'wildcard expression');
        }

        if ($prefix === null) {
            $usingValues = self::usingJoinWildcardValues($row);
            if ($usingValues !== null) {
                return $usingValues;
            }
        }

        $values = [];
        $matched = false;
        foreach ($row as $column => $value) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite SELECT projection row columns must be non-empty strings');
            }

            if ($prefix !== null) {
                $qualifiedPrefix = $prefix . '.';
                if (!str_starts_with($column, $qualifiedPrefix)) {
                    continue;
                }
                $matched = true;
                $alias = substr($column, strlen($qualifiedPrefix));
                if ($alias === '') {
                    throw new \InvalidArgumentException('SQLite SELECT wildcard projection matched an empty column name');
                }
                if (array_key_exists($alias, $values)) {
                    throw new \InvalidArgumentException("SQLite SELECT wildcard projection has duplicate column {$alias}");
                }
                $values[$alias] = $value;
                continue;
            }

            $matched = true;
            $values[$column] = $value;
        }

        if (!$matched) {
            $target = $prefix === null ? '*' : $prefix . '.*';
            throw new \InvalidArgumentException("SQLite SELECT wildcard projection matched no columns for {$target}");
        }

        return $values;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $expression
     * @return array<string,mixed>
     */
    private static function aggregateWildcardValues(array $row, array $expression): array
    {
        $prefix = null;
        if (array_key_exists('prefix', $expression)) {
            $prefix = self::requiredString($expression, 'prefix', 'wildcard expression');
        }

        $values = [];
        foreach ($expression['columns'] as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite SELECT aggregate wildcard projection columns must be non-empty strings');
            }

            $key = $prefix === null ? $column : $prefix . '.' . $column;
            $alias = $prefix === null ? $column : $column;
            $values[$alias] = array_key_exists($key, $row) ? $row[$key] : null;
        }

        return $values;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>|null
     */
    private static function usingJoinWildcardValues(array $row): ?array
    {
        $qualifiedAliases = [];
        foreach ($row as $column => $_value) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite SELECT projection row columns must be non-empty strings');
            }
            if (!str_contains($column, '.')) {
                continue;
            }

            $alias = substr($column, strrpos($column, '.') + 1);
            if ($alias !== '') {
                $qualifiedAliases[$alias] = true;
            }
        }

        $coalescedAliases = [];
        foreach ($row as $column => $_value) {
            if (!is_string($column) || $column === '' || str_contains($column, '.')) {
                continue;
            }
            if (isset($qualifiedAliases[$column])) {
                $coalescedAliases[$column] = true;
            }
        }
        if ($coalescedAliases === []) {
            return null;
        }

        $values = [];
        $emittedCoalesced = [];
        foreach ($row as $column => $value) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite SELECT projection row columns must be non-empty strings');
            }
            if (!str_contains($column, '.')) {
                continue;
            }

            $alias = substr($column, strrpos($column, '.') + 1);
            if ($alias === '') {
                throw new \InvalidArgumentException('SQLite SELECT wildcard projection matched an empty column name');
            }
            if (isset($coalescedAliases[$alias])) {
                if (isset($emittedCoalesced[$alias])) {
                    continue;
                }
                self::appendProjectedValue($values, $alias, $row[$alias]);
                $emittedCoalesced[$alias] = true;
                continue;
            }

            self::appendProjectedValue($values, $alias, $value);
        }

        foreach ($coalescedAliases as $alias => $_enabled) {
            if (!isset($emittedCoalesced[$alias]) && array_key_exists($alias, $row)) {
                self::appendProjectedValue($values, $alias, $row[$alias]);
            }
        }

        return $values;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $expression
     */
    private static function caseValue(array $row, array $expression): mixed
    {
        $base = null;
        $hasBase = array_key_exists('base', $expression);
        if ($hasBase) {
            $base = self::expressionValue($row, $expression['base']);
        }

        $branches = $expression['branches'] ?? null;
        if (!is_array($branches) || !array_is_list($branches) || $branches === []) {
            throw new \InvalidArgumentException('SQLite SELECT projection CASE expression needs non-empty branches');
        }

        foreach ($branches as $branch) {
            if (!is_array($branch) || !array_key_exists('when', $branch) || !array_key_exists('then', $branch)) {
                throw new \InvalidArgumentException('SQLite SELECT projection CASE branches need when and then expressions');
            }

            $when = self::expressionValue($row, $branch['when']);
            $matched = $hasBase ? self::caseBaseMatches($base, $when) : self::isSqlTrue($when);
            if ($matched) {
                return self::expressionValue($row, $branch['then']);
            }
        }

        return array_key_exists('else', $expression)
            ? self::expressionValue($row, $expression['else'])
            : null;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function expressionValue(array $row, mixed $expression): mixed
    {
        if (is_array($expression) && array_key_exists('type', $expression)) {
            return self::evaluateExpression($row, $expression);
        }

        return $expression;
    }

    private static function caseBaseMatches(mixed $base, mixed $when): bool
    {
        if ($base === null || $when === null) {
            return false;
        }

        return self::valueKey($base) === self::valueKey($when);
    }

    private static function isSqlTrue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        if ($value instanceof SQLiteBlobValue) {
            return self::numericPrefix($value->bytes) !== 0.0;
        }
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return (float) $value !== 0.0;
        }
        if (is_string($value)) {
            return self::numericPrefix($value) !== 0.0;
        }

        throw new \InvalidArgumentException('SQLite SELECT projection CASE values must be scalar, BLOB, or NULL');
    }

    private static function numericPrefix(string $value): float
    {
        $trimmed = ltrim($value);
        if (preg_match('/^[+-]?(?:(?:[0-9]+(?:\.[0-9]*)?)|(?:\.[0-9]+))(?:[eE][+-]?[0-9]+)?/', $trimmed, $match) !== 1) {
            return 0.0;
        }

        return (float) $match[0];
    }

    private static function valueKey(mixed $value): string
    {
        if ($value === null) {
            return 'null:';
        }
        if ($value instanceof SQLiteBlobValue) {
            return 'blob:' . $value->bytes;
        }
        if (is_bool($value) || is_int($value)) {
            return 'integer:' . (int) $value;
        }
        if (is_float($value)) {
            return 'real:' . sprintf('%.17G', $value);
        }
        if (is_string($value)) {
            return 'text:' . $value;
        }

        throw new \InvalidArgumentException('SQLite SELECT projection CASE values must be scalar, BLOB, or NULL');
    }

    /**
     * @param array<string,mixed> $projected
     */
    private static function appendProjectedValue(array &$projected, string $alias, mixed $value): string
    {
        if (!array_key_exists($alias, $projected)) {
            $projected[$alias] = $value;
            return $alias;
        }

        for ($suffix = 2; ; $suffix++) {
            $candidate = $alias . '#' . $suffix;
            if (!array_key_exists($candidate, $projected)) {
                $projected[$candidate] = $value;
                return $candidate;
            }
        }
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $expression
     */
    private static function projectedAffinity(array $row, array $expression): ?string
    {
        if (($expression['type'] ?? null) === 'collate' && isset($expression['operand']) && is_array($expression['operand'])) {
            return self::projectedAffinity($row, $expression['operand']);
        }
        if (($expression['type'] ?? null) !== 'column') {
            return null;
        }
        $name = $expression['name'] ?? null;
        if (!is_string($name) || $name === '') {
            return null;
        }
        $affinities = $row['__sqlite_column_affinities'] ?? null;
        if (!is_array($affinities)) {
            return null;
        }
        $candidates = [$name];
        if (str_contains($name, '.')) {
            $candidates[] = substr($name, strrpos($name, '.') + 1);
        }
        foreach ($candidates as $candidate) {
            $affinity = $affinities[$candidate] ?? null;
            if (is_string($affinity) && $affinity !== '') {
                return $affinity;
            }
        }

        return null;
    }

    private static function projectedValue(mixed $value): mixed
    {
        return $value instanceof SQLiteJsonSubtypeValue ? $value->json : $value;
    }

    /**
     * @param array<string,mixed> $expression
     */
    private static function expressionAlias(array $expression, int $index): string
    {
        if (array_key_exists('alias', $expression)) {
            if (!is_string($expression['alias']) || $expression['alias'] === '') {
                throw new \InvalidArgumentException('SQLite SELECT projection alias must be a non-empty string');
            }

            return $expression['alias'];
        }

        if (($expression['type'] ?? null) === 'column' && isset($expression['name']) && is_string($expression['name']) && $expression['name'] !== '') {
            return $expression['name'];
        }

        return 'expr' . ($index + 1);
    }

    /**
     * @param array<string,mixed> $expression
     */
    private static function requiredString(array $expression, string $key, string $context): string
    {
        if (!isset($expression[$key]) || !is_string($expression[$key]) || $expression[$key] === '') {
            throw new \InvalidArgumentException("SQLite SELECT projection {$context} needs {$key}");
        }

        return $expression[$key];
    }
}
