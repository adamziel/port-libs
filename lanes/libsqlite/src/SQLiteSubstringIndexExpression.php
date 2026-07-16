<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSubstringIndexExpression
{
    public function __construct(
        public readonly string $columnName,
        public readonly int $start,
        public readonly ?int $length,
        public readonly string $collation,
        public readonly bool $descending,
        public readonly bool $partial,
        public readonly ?SQLiteIndexPredicate $partialPredicate = null,
    ) {
    }
}
