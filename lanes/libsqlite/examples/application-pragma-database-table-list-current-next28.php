<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowId = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$catalog = new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_options', 'wp_options', 2, "CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT, autoload TEXT DEFAULT 'yes') STRICT", 1),
        $record('view', 'wp_autoloaded_options', 'wp_autoloaded_options', null, "CREATE VIEW wp_autoloaded_options AS SELECT option_name FROM wp_options WHERE autoload = 'yes'", 2),
    ],
    [
        $record('table', 'wp_options', 'wp_options', 3, 'CREATE TABLE wp_options(option_name TEXT PRIMARY KEY, option_value TEXT) WITHOUT ROWID', 1),
    ],
);
$catalog->attach('network', '/srv/www/network.sqlite', [
    $record('table', 'wp_sitemeta', 'wp_sitemeta', 4, 'CREATE TABLE wp_sitemeta(meta_id INTEGER PRIMARY KEY, meta_key TEXT, meta_value TEXT)', 1),
    $record('table', 'wp_options', 'wp_options', 5, 'CREATE TABLE wp_options(blog_id INTEGER, option_name TEXT, option_value TEXT, PRIMARY KEY(blog_id, option_name)) WITHOUT ROWID, STRICT', 2),
]);

$databases = $catalog->executeTableValuedPragmaCursor('pragma_database_list()');
$optionTables = $catalog->executeTableValuedPragmaCursor("pragma_table_list('wp_options')");
$firstDatabase = $databases->current()['name'];
$secondDatabase = $databases->next()['name'];
$databases->next();

echo json_encode([
    'applicationUse' => 'Stream copied Application attached database inventory and wp_options table metadata with SQLite table-valued PRAGMA current/next semantics without ext/sqlite.',
    'first_database' => $firstDatabase,
    'second_database' => $secondDatabase,
    'attached_databases' => array_column($databases->remainingRows(), 'name'),
    'first_wp_options_schema' => $optionTables->current()['schema'],
    'main_wp_options_schema' => $optionTables->next()['schema'],
    'network_wp_options' => $optionTables->next(),
], JSON_PRETTY_PRINT) . "\n";
