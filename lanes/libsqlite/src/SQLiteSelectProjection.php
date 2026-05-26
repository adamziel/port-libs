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
            default => throw new \InvalidArgumentException('SQLite SELECT projection expression type must be column, literal, or function'),
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
