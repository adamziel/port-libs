<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaDdlReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$records = [
    new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', 2, "CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT 'yes')", 1),
    new SQLiteSchemaRecord('index', 'sqlite_autoindex_wp_options_1', 'wp_options', 3, null, 2),
    new SQLiteSchemaRecord('index', 'wp_options_autoload', 'wp_options', 4, 'CREATE INDEX wp_options_autoload ON wp_options(autoload)', 3),
];

$plan = SQLiteSchemaDdlReparsePlan::apply(
    $records,
    [
        "CREATE VIEW wp_autoloaded_options AS SELECT option_id, option_name FROM wp_options WHERE autoload = 'yes'",
        "CREATE TRIGGER wp_autoloaded_insert INSTEAD OF INSERT ON wp_autoloaded_options BEGIN INSERT INTO wp_options(option_id, option_name, autoload) VALUES(new.option_id, new.option_name, 'yes'); END",
    ],
    70,
    'main',
    [
        ['id' => 'wp-option-import-select', 'schema_cookie' => 70, 'sql' => 'SELECT option_name FROM wp_options WHERE autoload = ?'],
    ],
);

echo json_encode([
    'schemaChanged' => $plan['schema_changed'],
    'beforeCookie' => $plan['before_schema_cookie'],
    'afterCookie' => $plan['after_schema_cookie'],
    'operations' => array_map(
        static fn (array $operation): array => [
            'kind' => $operation['kind'],
            'name' => $operation['name'],
            'table' => $operation['table'] ?? null,
            'rootpage' => $operation['rootpage'] ?? null,
        ],
        $plan['operations'],
    ),
    'invalidatedPrepared' => $plan['invalidated_prepared'],
    'recordNames' => array_map(static fn (SQLiteSchemaRecord $record): string => $record->type . ':' . $record->name, $plan['records']),
], JSON_PRETTY_PRINT) . "\n";
