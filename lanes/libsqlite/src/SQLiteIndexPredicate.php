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

    public function isImpliedByPointLookup(string $columnName, mixed $value, string $collation = 'BINARY'): bool
    {
        if ($this->operator === self::AND) {
            if (!is_array($this->value)) {
                return false;
            }

            foreach ($this->value as $predicate) {
                if (!$predicate instanceof self || !$predicate->isImpliedByPointLookup($columnName, $value, $collation)) {
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
                if ($predicate instanceof self && $predicate->isImpliedByPointLookup($columnName, $value, $collation)) {
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
            self::EQUALS => self::valuesEqual($this->value, $value, $collation),
            self::NOT_EQUALS => self::compareValuePredicate($value, $this->value, $collation, static fn (int $comparison): bool => $comparison !== 0),
            self::LESS_THAN => self::compareValuePredicate($value, $this->value, $collation, static fn (int $comparison): bool => $comparison < 0),
            self::LESS_THAN_OR_EQUAL => self::compareValuePredicate($value, $this->value, $collation, static fn (int $comparison): bool => $comparison <= 0),
            self::GREATER_THAN => self::compareValuePredicate($value, $this->value, $collation, static fn (int $comparison): bool => $comparison > 0),
            self::GREATER_THAN_OR_EQUAL => self::compareValuePredicate($value, $this->value, $collation, static fn (int $comparison): bool => $comparison >= 0),
            self::BETWEEN => is_array($this->value)
                && array_key_exists('lower', $this->value)
                && array_key_exists('upper', $this->value)
                && self::compareValuePredicate($value, $this->value['lower'], $collation, static fn (int $comparison): bool => $comparison >= 0)
                && self::compareValuePredicate($value, $this->value['upper'], $collation, static fn (int $comparison): bool => $comparison <= 0),
            default => false,
        };
    }

    public function isExpressionInListImpliedByPointLookup(string $columnName, mixed $value, string $collation = 'BINARY'): bool
    {
        if ($this->operator === self::AND) {
            return is_array($this->value)
                && $this->value !== []
                && array_reduce(
                    $this->value,
                    static fn (bool $carry, mixed $predicate): bool => $carry
                        && $predicate instanceof self
                        && $predicate->isExpressionInListImpliedByPointLookup($columnName, $value, $collation),
                    true
                );
        }
        if ($this->operator === self::OR) {
            return is_array($this->value)
                && array_reduce(
                    $this->value,
                    static fn (bool $carry, mixed $predicate): bool => $carry
                        || (
                            $predicate instanceof self
                            && $predicate->isExpressionInListImpliedByPointLookup($columnName, $value, $collation)
                        ),
                    false
                );
        }
        if ($this->operator !== self::IN_LIST || strcasecmp($this->columnName, $columnName) !== 0 || !is_array($this->value)) {
            return false;
        }

        foreach ($this->value as $predicateValue) {
            if (self::valuesEqual($predicateValue, $value, $collation)) {
                return true;
            }
        }

        return false;
    }

    public function isImpliedByRangeLookup(
        string $columnName,
        mixed $lowerInclusive,
        mixed $upperBound,
        bool $upperInclusive,
        string $collation = 'BINARY',
    ): bool {
        if ($this->operator === self::AND) {
            if (!is_array($this->value)) {
                return false;
            }

            foreach ($this->value as $predicate) {
                if (!$predicate instanceof self || !$predicate->isImpliedByRangeLookup($columnName, $lowerInclusive, $upperBound, $upperInclusive, $collation)) {
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
                if ($predicate instanceof self && $predicate->isImpliedByRangeLookup($columnName, $lowerInclusive, $upperBound, $upperInclusive, $collation)) {
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
            self::GREATER_THAN => self::rangeImpliesLowerBound($lowerInclusive, $this->value, false, $collation),
            self::GREATER_THAN_OR_EQUAL => self::rangeImpliesLowerBound($lowerInclusive, $this->value, true, $collation),
            self::LESS_THAN => self::rangeImpliesUpperBound($upperBound, $upperInclusive, $this->value, false, $collation),
            self::LESS_THAN_OR_EQUAL => self::rangeImpliesUpperBound($upperBound, $upperInclusive, $this->value, true, $collation),
            self::BETWEEN => is_array($this->value)
                && array_key_exists('lower', $this->value)
                && array_key_exists('upper', $this->value)
                && self::rangeImpliesLowerBound($lowerInclusive, $this->value['lower'], true, $collation)
                && self::rangeImpliesUpperBound($upperBound, $upperInclusive, $this->value['upper'], true, $collation),
            default => false,
        };
    }

    /**
     * @param list<mixed> $values
     */
    public function isImpliedByInListLookup(string $columnName, array $values, string $collation = 'BINARY'): bool
    {
        if ($this->operator === self::AND) {
            if (!is_array($this->value)) {
                return false;
            }

            foreach ($this->value as $predicate) {
                if (!$predicate instanceof self || !$predicate->isImpliedByInListLookup($columnName, $values, $collation)) {
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
                if ($predicate instanceof self && $predicate->isImpliedByInListLookup($columnName, $values, $collation)) {
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

        if (in_array($this->operator, [
            self::EQUALS,
            self::NOT_EQUALS,
            self::LESS_THAN,
            self::LESS_THAN_OR_EQUAL,
            self::GREATER_THAN,
            self::GREATER_THAN_OR_EQUAL,
            self::BETWEEN,
        ], true)) {
            $hasSearchValue = false;
            foreach ($values as $lookupValue) {
                if ($lookupValue === null) {
                    continue;
                }
                $hasSearchValue = true;
                if (!$this->isImpliedByPointLookup($columnName, $lookupValue, $collation)) {
                    return false;
                }
            }

            return $hasSearchValue;
        }

        if ($this->operator !== self::IN_LIST || !is_array($this->value)) {
            return false;
        }

        foreach ($values as $lookupValue) {
            if ($lookupValue === null) {
                continue;
            }
            $matched = false;
            foreach ($this->value as $predicateValue) {
                if (self::valuesEqual($predicateValue, $lookupValue, $collation)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return false;
            }
        }

        return true;
    }

    private static function valuesEqual(mixed $left, mixed $right, string $collation): bool
    {
        if (is_int($left) && is_int($right)) {
            return $left === $right;
        }
        if ((is_int($left) && is_float($right)) || (is_float($left) && is_int($right))) {
            $float = is_float($left) ? $left : $right;
            $integer = is_int($left) ? $left : $right;
            if ($float >= (float) (PHP_INT_MAX - 1024) || $float <= (float) (PHP_INT_MIN + 1024)) {
                return false;
            }

            return is_finite($float)
                && floor($float) === $float
                && (int) $float === $integer;
        }
        if (is_float($left) && is_float($right)) {
            return $left == $right;
        }

        if (is_string($left) && is_string($right)) {
            return self::compareValues($left, $right, $collation) === 0;
        }

        return $left === $right;
    }

    /**
     * @param callable(int): bool $accept
     */
    private static function compareValuePredicate(mixed $left, mixed $right, string $collation, callable $accept): bool
    {
        $comparison = self::compareValues($left, $right, $collation);

        return $comparison !== null && $accept($comparison);
    }

    private static function compareValues(mixed $left, mixed $right, string $collation): ?int
    {
        if ($left === null || $right === null) {
            return null;
        }
        if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
            return $left <=> $right;
        }
        if (is_string($left) && is_string($right)) {
            return match (strtoupper($collation)) {
                'NOCASE' => strcmp(self::asciiLower($left), self::asciiLower($right)),
                'RTRIM' => strcmp(rtrim($left, " \t\r\n"), rtrim($right, " \t\r\n")),
                default => strcmp($left, $right),
            };
        }

        return null;
    }

    private static function rangeImpliesLowerBound(mixed $lowerInclusive, mixed $predicateLower, bool $predicateInclusive, string $collation): bool
    {
        $comparison = self::compareValues($lowerInclusive, $predicateLower, $collation);
        if ($comparison === null) {
            return false;
        }
        if ($comparison > 0) {
            return true;
        }

        return $comparison === 0 && $predicateInclusive;
    }

    private static function rangeImpliesUpperBound(mixed $upperBound, bool $upperInclusive, mixed $predicateUpper, bool $predicateInclusive, string $collation): bool
    {
        $comparison = self::compareValues($upperBound, $predicateUpper, $collation);
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

    private static function asciiLower(string $value): string
    {
        $bytes = $value;
        $length = strlen($bytes);
        for ($i = 0; $i < $length; $i++) {
            $ord = ord($bytes[$i]);
            if ($ord >= 0x41 && $ord <= 0x5a) {
                $bytes[$i] = chr($ord + 0x20);
            }
        }

        return $bytes;
    }
}
