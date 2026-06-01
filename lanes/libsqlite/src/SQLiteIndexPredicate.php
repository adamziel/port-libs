<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteIndexPredicate
{
    public const IS_NOT_NULL = 'IS_NOT_NULL';
    public const EQUALS = 'EQUALS';
    public const NOT_EQUALS = 'NOT_EQUALS';
    public const LESS_THAN = 'LESS_THAN';
    public const LESS_THAN_OR_EQUAL = 'LESS_THAN_OR_EQUAL';
    public const GREATER_THAN = 'GREATER_THAN';
    public const GREATER_THAN_OR_EQUAL = 'GREATER_THAN_OR_EQUAL';
    public const BETWEEN = 'BETWEEN';
    public const IN_LIST = 'IN_LIST';
    public const AND = 'AND';
    public const OR = 'OR';

    public function __construct(
        public readonly string $columnName,
        public readonly string $operator,
        public readonly mixed $value = null,
    ) {
    }

    public function isImpliedByPointLookup(string $columnName, mixed $value): bool
    {
        if ($this->operator === self::AND) {
            if (!is_array($this->value)) {
                return false;
            }

            foreach ($this->value as $predicate) {
                if (!$predicate instanceof self || !$predicate->isImpliedByPointLookup($columnName, $value)) {
                    return false;
                }
            }

            return true;
        }

        if ($this->operator === self::OR) {
            if (!is_array($this->value)) {
                return false;
            }

            foreach ($this->value as $predicate) {
                if ($predicate instanceof self && $predicate->isImpliedByPointLookup($columnName, $value)) {
                    return true;
                }
            }

            return false;
        }

        if (strcasecmp($this->columnName, $columnName) !== 0) {
            return false;
        }

        return match ($this->operator) {
            self::IS_NOT_NULL => $value !== null,
            self::EQUALS => self::valuesEqual($this->value, $value),
            self::NOT_EQUALS => self::compareValuePredicate($value, $this->value, static fn (int $comparison): bool => $comparison !== 0),
            self::LESS_THAN => self::compareValuePredicate($value, $this->value, static fn (int $comparison): bool => $comparison < 0),
            self::LESS_THAN_OR_EQUAL => self::compareValuePredicate($value, $this->value, static fn (int $comparison): bool => $comparison <= 0),
            self::GREATER_THAN => self::compareValuePredicate($value, $this->value, static fn (int $comparison): bool => $comparison > 0),
            self::GREATER_THAN_OR_EQUAL => self::compareValuePredicate($value, $this->value, static fn (int $comparison): bool => $comparison >= 0),
            self::BETWEEN => is_array($this->value)
                && array_key_exists('lower', $this->value)
                && array_key_exists('upper', $this->value)
                && self::compareValuePredicate($value, $this->value['lower'], static fn (int $comparison): bool => $comparison >= 0)
                && self::compareValuePredicate($value, $this->value['upper'], static fn (int $comparison): bool => $comparison <= 0),
            default => false,
        };
    }

    public function isImpliedByRangeLookup(
        string $columnName,
        mixed $lowerInclusive,
        mixed $upperBound,
        bool $upperInclusive,
    ): bool {
        if ($this->operator === self::AND) {
            if (!is_array($this->value)) {
                return false;
            }

            foreach ($this->value as $predicate) {
                if (!$predicate instanceof self || !$predicate->isImpliedByRangeLookup($columnName, $lowerInclusive, $upperBound, $upperInclusive)) {
                    return false;
                }
            }

            return true;
        }

        if ($this->operator === self::OR) {
            if (!is_array($this->value)) {
                return false;
            }

            foreach ($this->value as $predicate) {
                if ($predicate instanceof self && $predicate->isImpliedByRangeLookup($columnName, $lowerInclusive, $upperBound, $upperInclusive)) {
                    return true;
                }
            }

            return false;
        }

        if (strcasecmp($this->columnName, $columnName) !== 0) {
            return false;
        }

        return match ($this->operator) {
            self::IS_NOT_NULL => $lowerInclusive !== null || $upperBound !== null,
            self::GREATER_THAN => self::rangeImpliesLowerBound($lowerInclusive, $this->value, false),
            self::GREATER_THAN_OR_EQUAL => self::rangeImpliesLowerBound($lowerInclusive, $this->value, true),
            self::LESS_THAN => self::rangeImpliesUpperBound($upperBound, $upperInclusive, $this->value, false),
            self::LESS_THAN_OR_EQUAL => self::rangeImpliesUpperBound($upperBound, $upperInclusive, $this->value, true),
            self::BETWEEN => is_array($this->value)
                && array_key_exists('lower', $this->value)
                && array_key_exists('upper', $this->value)
                && self::rangeImpliesLowerBound($lowerInclusive, $this->value['lower'], true)
                && self::rangeImpliesUpperBound($upperBound, $upperInclusive, $this->value['upper'], true),
            default => false,
        };
    }

    /**
     * @param list<mixed> $values
     */
    public function isImpliedByInListLookup(string $columnName, array $values): bool
    {
        if ($this->operator === self::AND) {
            if (!is_array($this->value)) {
                return false;
            }

            foreach ($this->value as $predicate) {
                if (!$predicate instanceof self || !$predicate->isImpliedByInListLookup($columnName, $values)) {
                    return false;
                }
            }

            return true;
        }

        if ($this->operator === self::OR) {
            if (!is_array($this->value)) {
                return false;
            }

            foreach ($this->value as $predicate) {
                if ($predicate instanceof self && $predicate->isImpliedByInListLookup($columnName, $values)) {
                    return true;
                }
            }

            return false;
        }

        if (strcasecmp($this->columnName, $columnName) !== 0) {
            return false;
        }

        if ($this->operator === self::IS_NOT_NULL) {
            foreach ($values as $value) {
                if ($value !== null) {
                    return true;
                }
            }

            return false;
        }

        if ($this->operator !== self::IN_LIST || !is_array($this->value) || count($this->value) !== count($values)) {
            return false;
        }

        foreach ($this->value as $index => $value) {
            if (!array_key_exists($index, $values) || $value !== $values[$index]) {
                return false;
            }
        }

        return true;
    }

    private static function valuesEqual(mixed $left, mixed $right): bool
    {
        if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
            return $left == $right;
        }

        return $left === $right;
    }

    /**
     * @param callable(int): bool $accept
     */
    private static function compareValuePredicate(mixed $left, mixed $right, callable $accept): bool
    {
        $comparison = self::compareValues($left, $right);

        return $comparison !== null && $accept($comparison);
    }

    private static function compareValues(mixed $left, mixed $right): ?int
    {
        if ($left === null || $right === null) {
            return null;
        }
        if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
            return $left <=> $right;
        }
        if (is_string($left) && is_string($right)) {
            return strcmp($left, $right);
        }

        return null;
    }

    private static function rangeImpliesLowerBound(mixed $lowerInclusive, mixed $predicateLower, bool $predicateInclusive): bool
    {
        $comparison = self::compareValues($lowerInclusive, $predicateLower);
        if ($comparison === null) {
            return false;
        }
        if ($comparison > 0) {
            return true;
        }

        return $comparison === 0 && $predicateInclusive;
    }

    private static function rangeImpliesUpperBound(mixed $upperBound, bool $upperInclusive, mixed $predicateUpper, bool $predicateInclusive): bool
    {
        $comparison = self::compareValues($upperBound, $predicateUpper);
        if ($comparison === null) {
            return false;
        }
        if ($comparison < 0) {
            return true;
        }
        if ($comparison > 0) {
            return false;
        }

        return !$upperInclusive || $predicateInclusive;
    }
}
