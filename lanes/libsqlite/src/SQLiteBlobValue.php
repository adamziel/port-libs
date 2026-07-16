<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBlobValue
{
    public function __construct(
        public readonly string $bytes,
    ) {
    }
}
