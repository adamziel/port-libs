<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteIndexPredicate
{
    public const IS_NOT_NULL = 'IS_NOT_NULL';

    public function __construct(
        public readonly string $columnName,
        public readonly string $operator,
    ) {
    }

    public function isImpliedByPointLookup(string $columnName, mixed $value): bool
    {
        if (strcasecmp($this->columnName, $columnName) !== 0) {
            return false;
        }

        return match ($this->operator) {
            self::IS_NOT_NULL => $value !== null,
            default => false,
        };
    }
}
