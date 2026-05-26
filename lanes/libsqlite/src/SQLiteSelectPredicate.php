<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSelectPredicate
{
    /**
     * @param iterable<array<string,mixed>> $rows
     * @param array<string,mixed> $predicate
     * @return list<array<string,mixed>>
     */
    public static function filter(iterable $rows, array $predicate): array
    {
        $result = [];
        foreach ($rows as $row) {
            self::assertRow($row);
            if (self::isTrue(self::evaluate($row, $predicate))) {
                $result[] = $row;
            }
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $predicate
     */
    public static function evaluate(array $row, array $predicate): mixed
    {
        $operator = strtoupper(self::requiredString($predicate, 'operator', 'SQLite SELECT predicate'));

        return match ($operator) {
            'AND' => self::andValue($row, $predicate['terms'] ?? null),
            'OR' => self::orValue($row, $predicate['terms'] ?? null),
            'NOT' => self::notValue($row, $predicate['term'] ?? null),
            '=', '==', 'IS' => self::compare($row, $predicate, static fn (int $comparison): bool => $comparison === 0, $operator === 'IS'),
            '!=', '<>', 'IS NOT' => self::compare($row, $predicate, static fn (int $comparison): bool => $comparison !== 0, $operator === 'IS NOT'),
            '<' => self::compare($row, $predicate, static fn (int $comparison): bool => $comparison < 0),
            '<=' => self::compare($row, $predicate, static fn (int $comparison): bool => $comparison <= 0),
            '>' => self::compare($row, $predicate, static fn (int $comparison): bool => $comparison > 0),
            '>=' => self::compare($row, $predicate, static fn (int $comparison): bool => $comparison >= 0),
            'BETWEEN' => self::between($row, $predicate, false),
            'NOT BETWEEN' => self::between($row, $predicate, true),
            'IN' => self::inList($row, $predicate, false),
            'NOT IN' => self::inList($row, $predicate, true),
            'LIKE' => self::like($row, $predicate, false),
            'NOT LIKE' => self::like($row, $predicate, true),
            'GLOB' => self::glob($row, $predicate, false),
            'NOT GLOB' => self::glob($row, $predicate, true),
            'IS NULL' => self::operand($row, $predicate, 'left') === null,
            'IS NOT NULL' => self::operand($row, $predicate, 'left') !== null,
            default => throw new \InvalidArgumentException("SQLite SELECT predicate operator {$operator} is not supported"),
        };
    }

    /**
     * @param array<string,mixed> $row
     * @param mixed $terms
     */
    private static function andValue(array $row, mixed $terms): ?bool
    {
        $terms = self::predicateList($terms, 'AND');
        $sawNull = false;
        foreach ($terms as $term) {
            $value = self::evaluate($row, $term);
            if (self::isFalse($value)) {
                return false;
            }
            if ($value === null) {
                $sawNull = true;
            }
        }

        return $sawNull ? null : true;
    }

    /**
     * @param array<string,mixed> $row
     * @param mixed $terms
     */
    private static function orValue(array $row, mixed $terms): ?bool
    {
        $terms = self::predicateList($terms, 'OR');
        $sawNull = false;
        foreach ($terms as $term) {
            $value = self::evaluate($row, $term);
            if (self::isTrue($value)) {
                return true;
            }
            if ($value === null) {
                $sawNull = true;
            }
        }

        return $sawNull ? null : false;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function notValue(array $row, mixed $term): ?bool
    {
        if (!is_array($term)) {
            throw new \InvalidArgumentException('SQLite SELECT NOT predicate needs a term');
        }

        $value = self::evaluate($row, $term);
        if ($value === null) {
            return null;
        }

        return !self::isTrue($value);
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $predicate
     * @param callable(int): bool $accept
     */
    private static function compare(array $row, array $predicate, callable $accept, bool $nullsEqual = false): ?bool
    {
        $left = self::operand($row, $predicate, 'left');
        $right = self::operand($row, $predicate, 'right');
        if ($left === null || $right === null) {
            return $nullsEqual ? $accept($left === $right ? 0 : 1) : null;
        }

        $comparison = self::compareValues($left, $right);
        if ($comparison === null && $nullsEqual) {
            return $accept(1);
        }

        return $comparison === null ? null : $accept($comparison);
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $predicate
     */
    private static function between(array $row, array $predicate, bool $negate): ?bool
    {
        $value = self::operand($row, $predicate, 'left');
        $lower = self::operand($row, $predicate, 'lower');
        $upper = self::operand($row, $predicate, 'upper');
        if ($value === null || $lower === null || $upper === null) {
            return null;
        }

        $lowerComparison = self::compareValues($value, $lower);
        $upperComparison = self::compareValues($value, $upper);
        if ($lowerComparison === null || $upperComparison === null) {
            return null;
        }

        $matched = $lowerComparison >= 0 && $upperComparison <= 0;

        return $negate ? !$matched : $matched;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $predicate
     */
    private static function inList(array $row, array $predicate, bool $negate): ?bool
    {
        $value = self::operand($row, $predicate, 'left');
        $values = $predicate['values'] ?? null;
        if (!is_array($values) || !array_is_list($values)) {
            throw new \InvalidArgumentException('SQLite SELECT IN predicate needs a list of values');
        }

        $sawNull = false;
        $matched = false;
        foreach ($values as $candidateExpression) {
            $candidate = self::valueExpression($row, $candidateExpression);
            if ($candidate === null) {
                $sawNull = true;
                continue;
            }
            if ($value !== null && self::compareValues($value, $candidate) === 0) {
                $matched = true;
                break;
            }
        }

        if ($negate) {
            if ($matched) {
                return false;
            }

            return $value === null || $sawNull ? null : true;
        }

        if ($matched) {
            return true;
        }

        return $value === null || $sawNull ? null : false;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $predicate
     */
    private static function like(array $row, array $predicate, bool $negate): ?bool
    {
        $left = self::operand($row, $predicate, 'left');
        $right = self::operand($row, $predicate, 'right');
        $escape = array_key_exists('escape', $predicate) ? self::valueExpression($row, $predicate['escape']) : null;
        if ($left === null || $right === null || $escape === null && array_key_exists('escape', $predicate) && $predicate['escape'] !== null) {
            return null;
        }
        if (!is_string($left) || !is_string($right) || ($escape !== null && !is_string($escape))) {
            throw new \InvalidArgumentException('SQLite SELECT LIKE predicate expects text operands');
        }

        $matched = SQLiteDatabase::likeMatches($left, $right, $escape, (bool) ($predicate['caseSensitive'] ?? false));

        return $negate ? !$matched : $matched;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $predicate
     */
    private static function glob(array $row, array $predicate, bool $negate): ?bool
    {
        $left = self::operand($row, $predicate, 'left');
        $right = self::operand($row, $predicate, 'right');
        if ($left === null || $right === null) {
            return null;
        }
        if (!is_string($left) || !is_string($right)) {
            throw new \InvalidArgumentException('SQLite SELECT GLOB predicate expects text operands');
        }

        $matched = SQLiteDatabase::globMatches($left, $right);

        return $negate ? !$matched : $matched;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $predicate
     */
    private static function operand(array $row, array $predicate, string $key): mixed
    {
        if (!array_key_exists($key, $predicate)) {
            throw new \InvalidArgumentException("SQLite SELECT predicate needs {$key} operand");
        }

        return self::valueExpression($row, $predicate[$key]);
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function valueExpression(array $row, mixed $expression): mixed
    {
        if (is_array($expression) && array_key_exists('column', $expression)) {
            $column = $expression['column'];
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite SELECT predicate column operands need a non-empty column name');
            }
            if (!array_key_exists($column, $row)) {
                throw new \InvalidArgumentException("SQLite SELECT predicate row is missing column {$column}");
            }

            return $row[$column];
        }

        if ($expression instanceof SQLiteBlobValue || $expression === null || is_bool($expression) || is_int($expression) || is_float($expression) || is_string($expression)) {
            return $expression;
        }

        throw new \InvalidArgumentException('SQLite SELECT predicate operands must be scalar, BLOB, NULL, or column references');
    }

    private static function compareValues(mixed $left, mixed $right): ?int
    {
        self::assertValue($left);
        self::assertValue($right);
        if ($left instanceof SQLiteBlobValue && $right instanceof SQLiteBlobValue) {
            return strcmp($left->bytes, $right->bytes);
        }
        if ((is_bool($left) || is_int($left) || is_float($left)) && (is_bool($right) || is_int($right) || is_float($right))) {
            return ((float) $left) <=> ((float) $right);
        }
        if (is_string($left) && is_string($right)) {
            return strcmp($left, $right);
        }

        return null;
    }

    private static function isTrue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value !== 0.0;
        }

        throw new \InvalidArgumentException('SQLite SELECT predicate truth values must be boolean, numeric, or NULL');
    }

    private static function isFalse(mixed $value): bool
    {
        return $value !== null && !self::isTrue($value);
    }

    /**
     * @param mixed $terms
     * @return list<array<string,mixed>>
     */
    private static function predicateList(mixed $terms, string $operator): array
    {
        if (!is_array($terms) || !array_is_list($terms) || $terms === []) {
            throw new \InvalidArgumentException("SQLite SELECT {$operator} predicate needs a non-empty term list");
        }
        foreach ($terms as $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException("SQLite SELECT {$operator} predicate terms must be predicates");
            }
        }

        return $terms;
    }

    private static function requiredString(array $payload, string $key, string $context): string
    {
        $value = $payload[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("{$context} needs {$key}");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function assertRow(array $row): void
    {
        foreach ($row as $column => $value) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite SELECT predicate rows must have named columns');
            }
            self::assertValue($value);
        }
    }

    private static function assertValue(mixed $value): void
    {
        if ($value instanceof SQLiteBlobValue || $value === null || is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            return;
        }

        throw new \InvalidArgumentException('SQLite SELECT predicate values must be scalar, BLOB, or NULL');
    }
}
