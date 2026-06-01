<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonExtractIndexExpression
{
    public function __construct(
        public readonly string $columnName,
        public readonly string $path,
        public readonly string $collation = 'BINARY',
        public readonly bool $descending = false,
        public readonly bool $partial = false,
        public readonly ?SQLiteIndexPredicate $partialPredicate = null,
    ) {
    }
}
