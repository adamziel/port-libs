<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteIndexPredicate
{
    public const IS_NOT_NULL = 'IS_NOT_NULL';
    public const EQUALS = 'EQUALS';
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
            default => false,
        };
    }

    private static function valuesEqual(mixed $left, mixed $right): bool
    {
        if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
            return $left == $right;
        }

        return $left === $right;
    }
}
