<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCreateTable;
use PortLibs\LibSqlite\SQLiteGeneratedColumnDependencyPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$schema = <<<'SQL'
CREATE TABLE wp_options(
    option_id INTEGER PRIMARY KEY,
    option_name TEXT NOT NULL,
    option_value TEXT,
    autoload TEXT DEFAULT 'yes',
    option_name_lower TEXT AS (lower(option_name)) VIRTUAL,
    autoload_rank INTEGER GENERATED ALWAYS AS (
        CASE autoload WHEN 'yes' THEN 1 WHEN 'no' THEN 2 ELSE 9 END
    ) STORED,
    option_kind TEXT AS (
        CASE
            WHEN option_name_lower LIKE '_transient_%' THEN 'transient'
            WHEN option_name_lower LIKE 'theme_%' THEN 'theme'
            ELSE 'option'
        END
    ) VIRTUAL,
    option_route TEXT GENERATED ALWAYS AS (
        CASE option_kind
            WHEN 'transient' THEN option_kind || ':' || option_name_lower
            ELSE option_kind || ':' || autoload_rank
        END
    ) STORED UNIQUE
)
SQL;

$analysis = SQLiteGeneratedColumnDependencyPlan::analyze($schema);
$autoIndexes = array_map(
    static fn (array $columns): array => array_map(static fn ($column): string => $column->columnName, $columns),
    SQLiteCreateTable::automaticIndexColumnMetadata($schema),
);

echo json_encode([
    'applicationUse' => 'Preview copied wp_options generated-column schemas whose CASE expressions derive cache routing columns, preserving dependency order and UNIQUE autoindex metadata without requiring ext/sqlite.',
    'status' => $analysis['status'],
    'table' => $analysis['table'],
    'evaluationOrder' => $analysis['order'],
    'routeDependencies' => $analysis['columns'][7]['dependencies'] ?? [],
    'autoIndexes' => $autoIndexes,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
