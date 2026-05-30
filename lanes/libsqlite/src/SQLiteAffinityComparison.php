<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteAffinityComparison
{
    public static function equals(mixed $left, mixed $right, string $leftAffinity = 'NONE', string $rightAffinity = 'NONE', string $collation = 'BINARY'): bool
    {
        return self::compare($left, $right, $leftAffinity, $rightAffinity, $collation) === 0;
    }

    public static function applyAffinity(mixed $value, string $affinity): mixed
    {
        self::assertComparable($value);

        return match (self::normalizeAffinity($affinity)) {
            'INTEGER', 'REAL', 'NUMERIC' => self::applyNumericAffinity($value),
            'TEXT' => self::applyTextAffinity($value),
            default => $value,
        };
    }

    /**
     * @return array{left:mixed,right:mixed,leftStorageClass:string,rightStorageClass:string}
     */
    public static function coercedPair(mixed $left, mixed $right, string $leftAffinity = 'NONE', string $rightAffinity = 'NONE'): array
    {
        self::assertComparable($left);
        self::assertComparable($right);

        $leftAffinity = self::normalizeAffinity($leftAffinity);
        $rightAffinity = self::normalizeAffinity($rightAffinity);

        if (self::isNumericAffinity($leftAffinity) && in_array($rightAffinity, ['TEXT', 'NONE', 'BLOB'], true)) {
            $right = self::applyNumericAffinity($right);
        } elseif (self::isNumericAffinity($rightAffinity) && in_array($leftAffinity, ['TEXT', 'NONE', 'BLOB'], true)) {
            $left = self::applyNumericAffinity($left);
        } elseif ($leftAffinity === 'TEXT' && $rightAffinity === 'NONE') {
            $right = self::applyTextAffinity($right);
        } elseif ($rightAffinity === 'TEXT' && $leftAffinity === 'NONE') {
            $left = self::applyTextAffinity($left);
        }

        return [
            'left' => $left,
            'right' => $right,
            'leftStorageClass' => self::storageClass($left),
            'rightStorageClass' => self::storageClass($right),
        ];
    }

    public static function compare(mixed $left, mixed $right, string $leftAffinity = 'NONE', string $rightAffinity = 'NONE', string $collation = 'BINARY'): ?int
    {
        $pair = self::coercedPair($left, $right, $leftAffinity, $rightAffinity);
        $left = $pair['left'];
        $right = $pair['right'];
        if ($left === null || $right === null) {
            return null;
        }

        $leftRank = self::sortRank($left);
        $rightRank = self::sortRank($right);
        if ($leftRank !== $rightRank) {
            return $leftRank <=> $rightRank;
        }
        if ($leftRank === 1) {
            $mixedNumeric = self::compareMixedIntegerReal($left, $right);
            if ($mixedNumeric !== null) {
                return $mixedNumeric;
            }

            return ((float) $left) <=> ((float) $right);
        }

        $leftText = $left instanceof SQLiteBlobValue ? $left->bytes : (string) $left;
        $rightText = $right instanceof SQLiteBlobValue ? $right->bytes : (string) $right;
        if ($leftRank === 2) {
            return self::compareText($leftText, $rightText, $collation);
        }

        return strcmp($leftText, $rightText);
    }

    public static function storageClass(mixed $value): string
    {
        self::assertComparable($value);

        if ($value === null) {
            return 'null';
        }
        if (is_bool($value) || is_int($value)) {
            return 'integer';
        }
        if (is_float($value)) {
            return 'real';
        }
        if (is_string($value)) {
            return 'text';
        }

        return 'blob';
    }

    private static function applyNumericAffinity(mixed $value): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        $text = $value instanceof SQLiteBlobValue ? $value->bytes : $value;
        $trimmed = trim($text);
        if (preg_match('/^[+-]?(?:(?:[0-9]+(?:\.[0-9]*)?)|(?:\.[0-9]+))(?:[eE][+-]?[0-9]+)?$/', $trimmed) !== 1) {
            return $value;
        }

        if (preg_match('/^[+-]?[0-9]+$/', $trimmed) === 1 && self::integerLiteralFitsInt64($trimmed)) {
            return (int) $trimmed;
        }

        $real = (float) $trimmed;

        return is_finite($real) && floor($real) === $real && preg_match('/[.eE]/', $trimmed) === 1 && self::integerLiteralFitsInt64(sprintf('%.0F', $real)) ? (int) $real : $real;
    }

    private static function applyTextAffinity(mixed $value): mixed
    {
        if ($value === null || is_string($value) || $value instanceof SQLiteBlobValue) {
            return $value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    private static function normalizeAffinity(string $affinity): string
    {
        $normalized = strtoupper($affinity);

        return match ($normalized) {
            'INT', 'INTEGER' => 'INTEGER',
            'REAL', 'FLOAT', 'DOUBLE' => 'REAL',
            'NUM', 'NUMERIC', 'BOOLEAN', 'DATE', 'DATETIME' => 'NUMERIC',
            'CHAR', 'CLOB', 'VARCHAR', 'TEXT' => 'TEXT',
            'BLOB', 'NONE', '' => 'NONE',
            default => throw new \InvalidArgumentException("SQLite comparison affinity {$affinity} is not supported"),
        };
    }

    private static function isNumericAffinity(string $affinity): bool
    {
        return $affinity === 'INTEGER' || $affinity === 'REAL' || $affinity === 'NUMERIC';
    }

    private static function sortRank(mixed $value): int
    {
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return 1;
        }
        if (is_string($value)) {
            return 2;
        }
        if ($value instanceof SQLiteBlobValue) {
            return 3;
        }

        throw new \InvalidArgumentException('SQLite comparison values must be scalar, BLOB, or NULL');
    }

    private static function compareText(string $left, string $right, string $collation): int
    {
        return match (strtoupper($collation)) {
            'BINARY' => strcmp($left, $right),
            'NOCASE' => strcmp(strtolower($left), strtolower($right)),
            'RTRIM' => strcmp(rtrim($left, ' '), rtrim($right, ' ')),
            default => throw new \InvalidArgumentException("SQLite comparison collation {$collation} is not supported"),
        };
    }

    private static function integerLiteralFitsInt64(string $literal): bool
    {
        $literal = trim($literal);
        $negative = str_starts_with($literal, '-');
        $digits = ltrim($literal, '+-');
        $digits = ltrim($digits, '0');
        if ($digits === '') {
            return true;
        }

        $limit = $negative ? '9223372036854775808' : '9223372036854775807';
        $length = strlen($digits);
        $limitLength = strlen($limit);
        if ($length !== $limitLength) {
            return $length < $limitLength;
        }

        return strcmp($digits, $limit) <= 0;
    }

    private static function compareMixedIntegerReal(mixed $left, mixed $right): ?int
    {
        if ((is_int($left) || is_bool($left)) && is_float($right)) {
            return self::compareIntegerToReal((int) $left, $right);
        }
        if (is_float($left) && (is_int($right) || is_bool($right))) {
            return -self::compareIntegerToReal((int) $right, $left);
        }

        return null;
    }

    private static function compareIntegerToReal(int $integer, float $real): int
    {
        if (!is_finite($real)) {
            return $real > 0.0 ? -1 : 1;
        }
        if ($integer === PHP_INT_MAX && $real >= 9223372036854775808.0) {
            return -1;
        }
        if ($integer === PHP_INT_MIN && $real <= -9223372036854775808.0) {
            return 0;
        }
        if ($real > 9223372036854775808.0) {
            return -1;
        }
        if ($real < -9223372036854775808.0) {
            return 1;
        }
        if (abs($integer) > 9007199254740992 && floor($real) === $real) {
            $realInteger = sprintf('%.0F', $real);
            if (self::integerLiteralFitsInt64($realInteger)) {
                $integerText = (string) $integer;

                return self::compareIntegerText($integerText, $realInteger);
            }
        }

        return ((float) $integer) <=> $real;
    }

    private static function compareIntegerText(string $left, string $right): int
    {
        $leftNegative = str_starts_with($left, '-');
        $rightNegative = str_starts_with($right, '-');
        if ($leftNegative !== $rightNegative) {
            return $leftNegative ? -1 : 1;
        }

        $leftDigits = ltrim($left, '+-0');
        $rightDigits = ltrim($right, '+-0');
        $leftDigits = $leftDigits === '' ? '0' : $leftDigits;
        $rightDigits = $rightDigits === '' ? '0' : $rightDigits;
        $comparison = strlen($leftDigits) <=> strlen($rightDigits);
        if ($comparison === 0) {
            $comparison = strcmp($leftDigits, $rightDigits);
        }

        return $leftNegative ? -($comparison <=> 0) : ($comparison <=> 0);
    }

    private static function assertComparable(mixed $value): void
    {
        if ($value instanceof SQLiteBlobValue || $value === null || is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            return;
        }

        throw new \InvalidArgumentException('SQLite comparison values must be scalar, BLOB, or NULL');
    }
}
