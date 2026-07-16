<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteSchemaRecord.php';
require_once __DIR__ . '/../src/SQLitePragmaSchemaCatalog.php';

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
            option_name TEXT NOT NULL DEFAULT '',
            option_value TEXT DEFAULT '',
            autoload TEXT DEFAULT 'yes',
            option_name_fold TEXT GENERATED ALWAYS AS (lower(option_name)) VIRTUAL,
            option_value_len INTEGER GENERATED ALWAYS AS (length(option_value)) STORED NOT NULL,
            option_cache_key TEXT AS (option_name || ':' || autoload),
            option_as_label 'AS TEXT' DEFAULT 'literal-as',
            option_current TEXT DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(option_name)
        )",
        1,
    ),
]);

$xinfo = $catalog->execute('PRAGMA table_xinfo(wp_options)')['rows'];
$info = $catalog->execute('PRAGMA table_info(wp_options)')['rows'];

echo json_encode([
    'status' => 'ok',
    'tableXinfoCount' => count($xinfo),
    'tableInfoCount' => count($info),
    'hiddenCodes' => array_column($xinfo, 'hidden', 'name'),
    'visibleColumns' => array_column($info, 'name'),
    'applicationUse' => 'Inspect copied wp_options generated-column metadata with PRAGMA table_xinfo while keeping literal AS type/default columns visible, without requiring ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
