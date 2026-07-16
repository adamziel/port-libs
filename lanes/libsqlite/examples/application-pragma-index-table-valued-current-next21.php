<?php

declare(strict_types=1);

require dirname(__DIR__) . '/../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowId = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowId,
);

$catalog = new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_options', 'wp_options', 2, "CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT 'yes')", 1),
        $record('index', 'wp_options_name_main', 'wp_options', 3, 'CREATE UNIQUE INDEX wp_options_name_main ON wp_options(option_name COLLATE NOCASE)', 2),
    ],
    [
        $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_name TEXT PRIMARY KEY, option_value TEXT, transient_timeout INTEGER)', 1),
        $record('index', 'wp_options_name_temp', 'wp_options', 5, 'CREATE INDEX wp_options_name_temp ON wp_options(option_name)', 2),
    ],
);
$catalog->attach('network', '/srv/www/network.sqlite', [
    $record('table', 'wp_sitemeta', 'wp_sitemeta', 6, 'CREATE TABLE wp_sitemeta(meta_id INTEGER PRIMARY KEY, meta_key TEXT, meta_value TEXT)', 1),
    $record('index', 'wp_sitemeta_key', 'wp_sitemeta', 7, 'CREATE INDEX wp_sitemeta_key ON wp_sitemeta(meta_key COLLATE RTRIM)', 2),
]);

$cursor = $catalog->executeTableValuedPragmaCursor("pragma_index_xinfo('wp_sitemeta_key')");

echo json_encode([
    'scenario' => 'copied wp_options table-valued pragma current source',
    'tempOptionsColumns' => array_column($catalog->executeTableValuedPragma("pragma_table_info('wp_options')")['rows'], 'name'),
    'mainOptionsIndexes' => array_column($catalog->executeTableValuedPragma("pragma_index_list('wp_options','main')")['rows'], 'name'),
    'networkIndexSchema' => $cursor->metadata()['schema'],
    'networkIndexFirstColumn' => $cursor->current()['name'],
    'networkIndexCollation' => $cursor->current()['coll'],
    'dependencies' => [
        'sqlite-table-valued-pragma-current-source',
        'sqlite-pragma-index-info',
        'sqlite-pragma-table-info',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
