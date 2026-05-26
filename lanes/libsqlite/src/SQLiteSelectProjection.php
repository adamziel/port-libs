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
            foreach ($expressions as $index => $expression) {
                $alias = self::expressionAlias($expression, $index);
                $projected[$alias] = self::evaluateExpression($row, $expression);
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

        return match ($kind) {
            'column' => self::columnValue($row, self::requiredString($expression, 'name', 'column expression')),
            'literal' => $expression['value'] ?? null,
            'function' => self::functionValue($row, $expression),
            'case' => self::caseValue($row, $expression),
            default => throw new \InvalidArgumentException('SQLite SELECT projection expression type must be column, literal, function, or case'),
        };
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $expression
     */
    private static function functionValue(array $row, array $expression): mixed
    {
        $function = self::requiredString($expression, 'name', 'function expression');
        $arguments = $expression['arguments'] ?? [];
        if (!is_array($arguments) || !array_is_list($arguments)) {
            throw new \InvalidArgumentException("SQLite SELECT projection function {$function} arguments must be a list");
        }

        $evaluated = [];
        foreach ($arguments as $argument) {
            if (is_array($argument) && array_key_exists('type', $argument)) {
                $evaluated[] = self::evaluateExpression($row, $argument);
                continue;
            }

            $evaluated[] = $argument;
        }

        return SQLiteCoreScalarFunction::sqlFunctionArguments($function, $evaluated);
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
     * @param array<string,mixed> $row
     */
    private static function columnValue(array $row, string $name): mixed
    {
        if (!array_key_exists($name, $row)) {
            throw new \InvalidArgumentException("SQLite SELECT projection row is missing column {$name}");
        }

        return $row[$name];
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
