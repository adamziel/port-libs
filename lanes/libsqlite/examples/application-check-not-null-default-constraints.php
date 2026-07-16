<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$catalog = new SQLitePragmaSchemaCatalog([
    new SQLiteSchemaRecord(
        'table',
        'wp_options',
        'wp_options',
        2,
        "CREATE TABLE wp_options(
            option_id INTEGER PRIMARY KEY,
            option_name TEXT NOT NULL DEFAULT '',
            option_value TEXT DEFAULT 'contains NOT NULL and CHECK text',
            autoload TEXT DEFAULT (coalesce('yes','no')) NOT NULL,
            protected INTEGER DEFAULT 0 CHECK(protected IN (0,1)),
            UNIQUE(option_name)
        )",
        1,
    ),
]);

$rows = $catalog->execute('PRAGMA table_info(wp_options)')['rows'];
$byName = [];
foreach ($rows as $row) {
    $byName[$row['name']] = [
        'notnull' => $row['notnull'],
        'default' => $row['dflt_value'],
        'pk' => $row['pk'],
    ];
}

echo json_encode([
    'applicationUse' => 'Preview wp_options schema metadata where CHECK, NOT NULL, and DEFAULT constraints are parsed for PRAGMA table_info() without ext/sqlite.',
    'table' => 'wp_options',
    'columns' => $byName,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
