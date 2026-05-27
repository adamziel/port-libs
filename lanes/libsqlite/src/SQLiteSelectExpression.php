<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSelectExpression
{
    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $expression
     */
    public static function evaluate(array $row, array $expression): mixed
    {
        $kind = $expression['type'] ?? null;

        return match ($kind) {
            'column' => self::columnValue($row, self::requiredString($expression, 'name', 'column expression')),
            'literal' => $expression['value'] ?? null,
            'function' => self::functionValue($row, $expression),
            'binary' => self::binaryValue($row, $expression),
            default => throw new \InvalidArgumentException('SQLite SELECT expression type must be column, literal, function, or binary'),
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
            throw new \InvalidArgumentException("SQLite SELECT expression function {$function} arguments must be a list");
        }

        $evaluated = [];
        foreach ($arguments as $argument) {
            if (is_array($argument)) {
                $evaluated[] = self::evaluate($row, $argument);
                continue;
            }

            $evaluated[] = $argument;
        }

        $normalized = strtolower($function);
        if ($normalized === 'json_extract' || $normalized === 'jsonb_extract') {
            if ($evaluated === []) {
                throw new \InvalidArgumentException('SQLite SELECT expression json_extract() requires a JSON argument');
            }

            $value = array_shift($evaluated);
            if ($value !== null && !is_string($value) && !$value instanceof SQLiteBlobValue) {
                throw new \InvalidArgumentException('SQLite SELECT expression json_extract() JSON argument must be text, JSONB, or NULL');
            }

            $paths = [];
            foreach ($evaluated as $path) {
                if (!is_string($path)) {
                    throw new \InvalidArgumentException('SQLite SELECT expression json_extract() paths must be text');
                }
                $paths[] = $path;
            }

            return SQLiteJsonExtract::extractSqlFunction($normalized, $value, ...$paths);
        }

        return SQLiteCoreScalarFunction::sqlFunctionArguments($function, $evaluated);
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $expression
     */
    private static function binaryValue(array $row, array $expression): mixed
    {
        $operator = self::requiredString($expression, 'operator', 'binary expression');
        $leftExpression = $expression['left'] ?? null;
        $rightExpression = $expression['right'] ?? null;
        if (!is_array($leftExpression) || !is_array($rightExpression)) {
            throw new \InvalidArgumentException("SQLite SELECT expression {$operator} needs expression operands");
        }

        $left = self::evaluate($row, $leftExpression);
        $right = self::evaluate($row, $rightExpression);
        if ($left === null || $right === null) {
            return null;
        }

        return match ($operator) {
            '||' => self::textValue($left) . self::textValue($right),
            '+', '-', '*', '/', '%' => self::numericValue($left, $right, $operator),
            default => throw new \InvalidArgumentException("SQLite SELECT expression operator {$operator} is not supported"),
        };
    }

    private static function numericValue(mixed $left, mixed $right, string $operator): int|float|null
    {
        $leftNumeric = self::numericOperand($left);
        $rightNumeric = self::numericOperand($right);
        if ($operator === '/' && $rightNumeric == 0) {
            return null;
        }
        if ($operator === '%' && (int) $rightNumeric === 0) {
            return null;
        }

        return match ($operator) {
            '+' => self::integerLike($leftNumeric, $rightNumeric) ? (int) $leftNumeric + (int) $rightNumeric : $leftNumeric + $rightNumeric,
            '-' => self::integerLike($leftNumeric, $rightNumeric) ? (int) $leftNumeric - (int) $rightNumeric : $leftNumeric - $rightNumeric,
            '*' => self::integerLike($leftNumeric, $rightNumeric) ? (int) $leftNumeric * (int) $rightNumeric : $leftNumeric * $rightNumeric,
            '/' => $leftNumeric / $rightNumeric,
            '%' => (int) $leftNumeric % (int) $rightNumeric,
            default => throw new \InvalidArgumentException("SQLite SELECT numeric operator {$operator} is not supported"),
        };
    }

    private static function numericOperand(mixed $value): int|float
    {
        if (is_bool($value) || is_int($value)) {
            return (int) $value;
        }
        if (is_float($value)) {
            return $value;
        }
        if ($value instanceof SQLiteBlobValue) {
            return self::numericPrefix($value->bytes);
        }
        if (is_string($value)) {
            return self::numericPrefix($value);
        }

        throw new \InvalidArgumentException('SQLite SELECT numeric expression operands must be scalar, BLOB, or NULL');
    }

    private static function numericPrefix(string $value): int|float
    {
        $trimmed = ltrim($value);
        if (preg_match('/^[+-]?(?:(?:[0-9]+(?:\.[0-9]*)?)|(?:\.[0-9]+))(?:[eE][+-]?[0-9]+)?/', $trimmed, $match) !== 1) {
            return 0;
        }
        if (preg_match('/^[+-]?[0-9]+$/', $match[0]) === 1) {
            return (int) $match[0];
        }

        return (float) $match[0];
    }

    private static function integerLike(int|float $left, int|float $right): bool
    {
        return is_int($left) && is_int($right);
    }

    private static function textValue(mixed $value): string
    {
        if ($value instanceof SQLiteBlobValue) {
            return $value->bytes;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value) || is_string($value)) {
            return (string) $value;
        }

        throw new \InvalidArgumentException('SQLite SELECT concatenation operands must be scalar, BLOB, or NULL');
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function columnValue(array $row, string $name): mixed
    {
        if (!array_key_exists($name, $row)) {
            throw new \InvalidArgumentException("SQLite SELECT expression row is missing column {$name}");
        }

        return $row[$name];
    }

    /**
     * @param array<string,mixed> $expression
     */
    private static function requiredString(array $expression, string $key, string $context): string
    {
        if (!isset($expression[$key]) || !is_string($expression[$key]) || $expression[$key] === '') {
            throw new \InvalidArgumentException("SQLite SELECT expression {$context} needs {$key}");
        }

        return $expression[$key];
    }
}
