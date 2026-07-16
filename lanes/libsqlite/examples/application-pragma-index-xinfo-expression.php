<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$catalog = new SQLitePragmaSchemaCatalog([
    new SQLiteSchemaRecord(
        'table',
        'wp_options',
        'wp_options',
        2,
        "CREATE TABLE wp_options(
            option_id INTEGER PRIMARY KEY,
            option_name TEXT NOT NULL,
            option_value TEXT,
            autoload TEXT DEFAULT 'yes'
        )",
        1,
    ),
    new SQLiteSchemaRecord(
        'index',
        'wp_options_plugin_expr',
        'wp_options',
        3,
        "CREATE INDEX wp_options_plugin_expr ON wp_options(lower(option_name) COLLATE nocase DESC, json_extract(option_value, '$.plugin.enabled'), autoload) WHERE autoload = 'yes'",
        2,
    ),
]);

$xinfo = $catalog->execute('PRAGMA index_xinfo(wp_options_plugin_expr)')['rows'];

echo json_encode([
    'applicationUse' => 'Preview PRAGMA index_xinfo rows for copied wp_options expression indexes so schema diagnostics can distinguish expression terms from table columns without ext/sqlite.',
    'index' => 'wp_options_plugin_expr',
    'keyTerms' => array_values(array_filter($xinfo, static fn (array $row): bool => $row['key'] === 1)),
    'auxiliaryTerms' => array_values(array_filter($xinfo, static fn (array $row): bool => $row['key'] === 0)),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
