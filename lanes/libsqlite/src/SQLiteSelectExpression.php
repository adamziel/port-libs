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
            'collate' => self::collateValue($row, $expression),
            'function' => self::functionValue($row, $expression),
            'cast' => self::castValue($row, $expression),
            'unary' => self::unaryValue($row, $expression),
            'binary' => self::binaryValue($row, $expression),
            'row' => self::rowValue($row, $expression),
            'subquery' => self::subqueryValue($row, $expression),
            default => throw new \InvalidArgumentException('SQLite SELECT expression type must be column, literal, collate, function, cast, unary, binary, row, or subquery'),
        };
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $expression
     */
    private static function collateValue(array $row, array $expression): mixed
    {
        $operand = $expression['operand'] ?? null;
        if (!is_array($operand)) {
            throw new \InvalidArgumentException('SQLite SELECT COLLATE expression needs an operand');
        }

        return self::evaluate($row, $operand);
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $expression
     * @return list<mixed>
     */
    private static function rowValue(array $row, array $expression): array
    {
        $values = $expression['values'] ?? null;
        if (!is_array($values) || !array_is_list($values) || count($values) < 2) {
            throw new \InvalidArgumentException('SQLite SELECT row-value expression needs at least two values');
        }

        $evaluated = [];
        foreach ($values as $valueExpression) {
            if (!is_array($valueExpression)) {
                throw new \InvalidArgumentException('SQLite SELECT row-value expression values must be expressions');
            }
            $evaluated[] = self::evaluate($row, $valueExpression);
        }

        return $evaluated;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $expression
     */
    private static function subqueryValue(array $row, array $expression): mixed
    {
        $subquery = $expression['subquery'] ?? null;
        if (!is_callable($subquery)) {
            throw new \InvalidArgumentException('SQLite SELECT scalar subquery expression needs a callable');
        }

        $rows = $subquery($row);
        if (!is_array($rows)) {
            throw new \InvalidArgumentException('SQLite SELECT scalar subquery expression must return rows');
        }
        if ($rows === []) {
            return null;
        }
        $first = $rows[0];
        if (!is_array($first)) {
            throw new \InvalidArgumentException('SQLite SELECT scalar subquery expression rows must be arrays');
        }

        $columns = array_keys($first);
        if (count($columns) !== 1) {
            throw new \InvalidArgumentException('SQLite SELECT scalar subquery expression must return one column');
        }

        return $first[$columns[0]];
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $expression
     */
    private static function unaryValue(array $row, array $expression): mixed
    {
        $operator = self::requiredString($expression, 'operator', 'unary expression');
        $operandExpression = $expression['operand'] ?? null;
        if (!is_array($operandExpression)) {
            throw new \InvalidArgumentException("SQLite SELECT expression unary {$operator} needs an expression operand");
        }

        $operand = self::evaluate($row, $operandExpression);
        if ($operand === null) {
            return null;
        }

        return match ($operator) {
            '+' => self::numericOperand($operand),
            '-' => -self::numericOperand($operand),
            '~' => ~self::integerOperand($operand),
            default => throw new \InvalidArgumentException("SQLite SELECT unary expression operator {$operator} is not supported"),
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
                if ($path === null) {
                    return null;
                }
                $paths[] = self::jsonPathText($path, 'json_extract()');
            }

            return SQLiteJsonExtract::extractSqlFunction($normalized, $value, ...$paths);
        }
        if ($normalized === 'json_type' || $normalized === 'json_array_length') {
            if (count($evaluated) < 1 || count($evaluated) > 2) {
                throw new \InvalidArgumentException('SQLite SELECT expression json_type() and json_array_length() expect one or two arguments');
            }

            $value = $evaluated[0];
            if ($value !== null && !is_string($value) && !$value instanceof SQLiteBlobValue && !$value instanceof SQLiteJsonSubtypeValue) {
                throw new \InvalidArgumentException('SQLite SELECT expression JSON inspection argument must be text, JSONB, JSON subtype, or NULL');
            }

            $path = array_key_exists(1, $evaluated) ? $evaluated[1] : '$';
            if ($path !== null) {
                $path = self::jsonPathText($path, 'JSON inspection');
            }

            return SQLiteJsonInspection::inspectionSqlFunction($normalized, $value, $path);
        }

        return SQLiteCoreScalarFunction::sqlFunctionArguments($function, $evaluated);
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $expression
     */
    private static function castValue(array $row, array $expression): mixed
    {
        $operandExpression = $expression['operand'] ?? null;
        if (!is_array($operandExpression)) {
            throw new \InvalidArgumentException('SQLite SELECT CAST expression needs an operand');
        }
        $target = strtolower(self::requiredString($expression, 'target', 'CAST expression'));
        $value = self::evaluate($row, $operandExpression);
        if ($value === null) {
            return null;
        }

        return match ($target) {
            'int', 'integer' => self::integerOperand($value),
            'real', 'float', 'double' => (float) self::numericOperand($value),
            'numeric' => self::numericOperand($value),
            'text', 'char', 'clob', 'varchar' => self::textValue($value),
            'blob', 'none' => new SQLiteBlobValue(self::textValue($value)),
            default => throw new \InvalidArgumentException("SQLite SELECT CAST target {$target} is not supported"),
        };
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
            '&', '|', '<<', '>>' => self::bitwiseValue($left, $right, $operator),
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

    private static function bitwiseValue(mixed $left, mixed $right, string $operator): int
    {
        $leftInteger = self::integerOperand($left);
        $rightInteger = self::integerOperand($right);

        return match ($operator) {
            '&' => $leftInteger & $rightInteger,
            '|' => $leftInteger | $rightInteger,
            '<<' => $leftInteger << $rightInteger,
            '>>' => $leftInteger >> $rightInteger,
            default => throw new \InvalidArgumentException("SQLite SELECT bitwise operator {$operator} is not supported"),
        };
    }

    private static function integerOperand(mixed $value): int
    {
        return (int) self::numericOperand($value);
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

    private static function jsonPathText(mixed $value, string $context): string
    {
        if ($value instanceof SQLiteBlobValue || is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            return self::textValue($value);
        }

        throw new \InvalidArgumentException("SQLite SELECT expression {$context} path must have scalar, BLOB, or NULL affinity");
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function columnValue(array $row, string $name): mixed
    {
        if (array_key_exists($name, $row)) {
            return $row[$name];
        }

        if (!str_contains($name, '.')) {
            $matches = [];
            $suffix = '.' . $name;
            foreach ($row as $column => $value) {
                if (is_string($column) && str_ends_with($column, $suffix)) {
                    $matches[] = $value;
                }
            }
            if (count($matches) === 1) {
                return $matches[0];
            }
            if (count($matches) > 1) {
                throw new \InvalidArgumentException("SQLite SELECT expression column {$name} is ambiguous");
            }
        }

        throw new \InvalidArgumentException("SQLite SELECT expression row is missing column {$name}");
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
