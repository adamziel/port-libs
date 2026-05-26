<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCoreScalarFunction
{
    /**
     * @param list<mixed> $arguments
     */
    public static function sqlFunctionArguments(string $functionName, array $arguments): mixed
    {
        $normalized = strtolower($functionName);

        return match ($normalized) {
            'abs' => self::abs($arguments),
            'round' => self::round($arguments),
            'typeof' => self::typeof($arguments),
            'quote' => self::quote($arguments),
            'coalesce' => self::coalesce($arguments),
            'ifnull' => self::ifnull($arguments),
            'nullif' => self::nullif($arguments),
            default => throw new \InvalidArgumentException("Unsupported SQLite core scalar function: {$functionName}"),
        };
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function abs(array $arguments): int|float|null
    {
        self::assertArity('abs', $arguments, 1, 1);
        if ($arguments[0] === null) {
            return null;
        }

        $number = self::coerceNumeric($arguments[0]);
        if (is_int($number)) {
            if ($number === PHP_INT_MIN) {
                throw new \OverflowException('SQLite abs() integer overflow');
            }

            return abs($number);
        }

        return abs($number);
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function round(array $arguments): ?float
    {
        self::assertArity('round', $arguments, 1, 2);
        if ($arguments[0] === null || (array_key_exists(1, $arguments) && $arguments[1] === null)) {
            return null;
        }

        $precision = array_key_exists(1, $arguments) ? self::coerceInteger($arguments[1]) : 0;
        if ($precision < 0) {
            $precision = 0;
        }

        return round((float) self::coerceNumeric($arguments[0]), $precision, PHP_ROUND_HALF_UP);
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function typeof(array $arguments): string
    {
        self::assertArity('typeof', $arguments, 1, 1);

        return match (true) {
            $arguments[0] === null => 'null',
            $arguments[0] instanceof SQLiteBlobValue => 'blob',
            is_int($arguments[0]) || is_bool($arguments[0]) => 'integer',
            is_float($arguments[0]) => 'real',
            is_string($arguments[0]) => 'text',
            default => throw new \InvalidArgumentException('SQLite typeof() argument must be scalar, BLOB, or NULL'),
        };
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function quote(array $arguments): string
    {
        self::assertArity('quote', $arguments, 1, 1);
        $value = $arguments[0];
        if ($value === null) {
            return 'NULL';
        }
        if ($value instanceof SQLiteBlobValue) {
            return "X'" . strtoupper(bin2hex($value->bytes)) . "'";
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            if (is_nan($value)) {
                return 'NULL';
            }
            if (is_infinite($value)) {
                return $value > 0 ? 'Inf' : '-Inf';
            }

            return self::formatFloat($value);
        }
        if (is_string($value)) {
            return "'" . str_replace("'", "''", $value) . "'";
        }

        throw new \InvalidArgumentException('SQLite quote() argument must be scalar, BLOB, or NULL');
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function coalesce(array $arguments): mixed
    {
        self::assertArity('coalesce', $arguments, 2, null);
        foreach ($arguments as $argument) {
            if ($argument !== null) {
                return $argument;
            }
        }

        return null;
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function ifnull(array $arguments): mixed
    {
        self::assertArity('ifnull', $arguments, 2, 2);

        return $arguments[0] ?? $arguments[1];
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function nullif(array $arguments): mixed
    {
        self::assertArity('nullif', $arguments, 2, 2);

        return self::sqliteValuesEqual($arguments[0], $arguments[1]) ? null : $arguments[0];
    }

    private static function assertArity(string $functionName, array $arguments, int $minimum, ?int $maximum): void
    {
        $count = count($arguments);
        if ($count < $minimum || ($maximum !== null && $count > $maximum)) {
            $range = $maximum === null ? "{$minimum}+" : ($minimum === $maximum ? (string) $minimum : "{$minimum}-{$maximum}");
            throw new \InvalidArgumentException("SQLite {$functionName}() expects {$range} argument(s), {$count} given");
        }
    }

    private static function coerceNumeric(mixed $value): int|float
    {
        if ($value instanceof SQLiteBlobValue) {
            $value = $value->bytes;
        }
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_string($value)) {
            $text = ltrim($value);
            if (preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?/', $text, $matches) === 1) {
                return str_contains($matches[0], '.') || stripos($matches[0], 'e') !== false
                    ? (float) $matches[0]
                    : (int) $matches[0];
            }

            return 0;
        }

        throw new \InvalidArgumentException('SQLite numeric scalar argument must be scalar, BLOB, or NULL');
    }

    private static function coerceInteger(mixed $value): int
    {
        $number = self::coerceNumeric($value);

        return (int) $number;
    }

    private static function sqliteValuesEqual(mixed $left, mixed $right): bool
    {
        if ($left === null || $right === null) {
            return false;
        }
        if ($left instanceof SQLiteBlobValue || $right instanceof SQLiteBlobValue) {
            return $left instanceof SQLiteBlobValue
                && $right instanceof SQLiteBlobValue
                && $left->bytes === $right->bytes;
        }
        if ((is_int($left) || is_float($left) || is_bool($left)) && (is_int($right) || is_float($right) || is_bool($right))) {
            return (float) $left === (float) $right;
        }
        if (is_string($left) && is_string($right)) {
            return $left === $right;
        }

        return false;
    }

    private static function formatFloat(float $value): string
    {
        $formatted = sprintf('%.15G', $value);

        return str_contains($formatted, 'E') ? str_replace('E', 'e', $formatted) : $formatted;
    }
}
