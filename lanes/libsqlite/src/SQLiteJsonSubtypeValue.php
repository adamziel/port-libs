<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonSubtypeValue
{
    public function __construct(public readonly string $json)
    {
    }
}
