<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$schema = <<<'SQL'
CREATE TABLE wp_options(
    option_id INTEGER PRIMARY KEY,
    option_name TEXT NOT NULL,
    option_value TEXT DEFAULT '',
    autoload TEXT DEFAULT 'yes',
    option_name_fold TEXT AS (lower(option_name || ' CHECK UNIQUE ')) VIRTUAL CHECK(option_name_fold <> ''),
    option_value_len INTEGER GENERATED ALWAYS AS (length(option_value)) STORED,
    UNIQUE(option_name)
)
SQL;

$catalog = new SQLitePragmaSchemaCatalog([
    new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', 2, $schema, 1),
    new SQLiteSchemaRecord('index', 'sqlite_autoindex_wp_options_1', 'wp_options', 3, null, 2),
]);

echo json_encode([
    'tableInfoColumns' => array_column($catalog->execute('PRAGMA table_info(wp_options)')['rows'], 'name'),
    'tableXinfoHidden' => array_column($catalog->execute('PRAGMA table_xinfo(wp_options)')['rows'], 'hidden'),
    'autoIndexColumns' => array_column($catalog->execute('PRAGMA index_info(sqlite_autoindex_wp_options_1)')['rows'], 'name'),
    'applicationUse' => 'Inspect Application-style generated wp_options metadata while ignoring CHECK/UNIQUE text inside generated-column expressions, without requiring ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
