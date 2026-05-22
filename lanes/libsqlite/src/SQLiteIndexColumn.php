<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteIndexColumn
{
    public function __construct(
        public readonly string $columnName,
        public readonly string $collation,
        public readonly bool $descending,
        public readonly bool $partial,
        public readonly ?SQLiteIndexPredicate $partialPredicate = null,
    ) {
    }
}
