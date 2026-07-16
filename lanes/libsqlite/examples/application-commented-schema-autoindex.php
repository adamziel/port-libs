<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCreateTable;
use PortLibs\LibSqlite\SQLiteIndexColumn;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sql = <<<'SQL'
CREATE TABLE wp_options(
    option_id INTEGER PRIMARY KEY, -- rowid alias, not a separate autoindex
    option_name TEXT /* copied dump comment */ UNIQUE,
    option_value TEXT CHECK(option_value <> '--literal'),
    autoload TEXT,
    CONSTRAINT uq_autoload_name UNIQUE(autoload, option_name COLLATE nocase DESC)
) /* dump footer */ WITHOUT /* table option gap */ ROWID
SQL;

$indexes = SQLiteCreateTable::automaticIndexColumnMetadata($sql);

echo json_encode([
    'automaticIndexes' => array_map(
        static fn (array $columns): array => array_map(
            static fn (SQLiteIndexColumn $column): array => [
                'column' => $column->columnName,
                'collation' => $column->collation,
                'descending' => $column->descending,
            ],
            $columns,
        ),
        $indexes,
    ),
], JSON_PRETTY_PRINT) . "\n";
