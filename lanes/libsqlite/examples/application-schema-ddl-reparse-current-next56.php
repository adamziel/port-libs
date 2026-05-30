<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSchemaDdlReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$records = [
    new SQLiteSchemaRecord(
        'table',
        'wp_options',
        'wp_options',
        2,
        "CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT 'yes')",
        1,
    ),
    new SQLiteSchemaRecord('index', 'sqlite_autoindex_wp_options_1', 'wp_options', 3, null, 2),
];

$plan = SQLiteSchemaDdlReparsePlan::apply(
    $records,
    [
        "CREATE UNIQUE INDEX wp_options_autoload_name ON wp_options(autoload, option_name) WHERE autoload = 'yes'",
        'CREATE TABLE wp_optionmeta(meta_id INTEGER PRIMARY KEY, option_id INTEGER NOT NULL, meta_key TEXT, meta_value TEXT)',
    ],
    56,
    'main',
    [
        ['id' => 'wp-options-import-select', 'schema_cookie' => 56, 'sql' => 'SELECT option_name FROM wp_options WHERE autoload = ?'],
    ],
);

echo json_encode([
    'scenario' => 'copied wp_options schema DDL reparse current next56',
    'applicationUse' => 'Reprepare copied Application import statements after sqlite_schema DDL changes without ext/sqlite.',
    'schemaCookie' => [
        'before' => $plan['before_schema_cookie'],
        'after' => $plan['after_schema_cookie'],
    ],
    'operations' => array_map(
        static fn (array $operation): array => [
            'kind' => $operation['kind'],
            'name' => $operation['name'] ?? ($operation['new_name'] ?? null),
            'changed' => $operation['changed'],
        ],
        $plan['operations'],
    ),
    'invalidatedPrepared' => $plan['invalidated_prepared'],
    'tableCount' => $plan['table_count'],
    'indexCount' => $plan['index_count'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
