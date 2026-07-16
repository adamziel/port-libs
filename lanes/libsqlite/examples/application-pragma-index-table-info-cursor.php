<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowId = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$catalog = new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_options', 'wp_options', 2, "CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, autoload TEXT DEFAULT 'yes')", 1),
        $record('index', 'wp_options_name_main', 'wp_options', 3, 'CREATE INDEX wp_options_name_main ON wp_options(option_name)', 2),
    ],
    [
        $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_name TEXT, option_value TEXT, transient_timeout INTEGER)', 1),
        $record('index', 'wp_options_name_temp', 'wp_options', 5, 'CREATE INDEX wp_options_name_temp ON wp_options(option_name)', 2),
    ],
);
$catalog->attach('site', '/srv/www/site.sqlite', [
    $record('table', 'wp_sitemeta', 'wp_sitemeta', 6, 'CREATE TABLE wp_sitemeta(meta_id INTEGER PRIMARY KEY, meta_key TEXT, meta_value TEXT)', 1),
    $record('index', 'wp_sitemeta_key', 'wp_sitemeta', 7, 'CREATE INDEX wp_sitemeta_key ON wp_sitemeta(meta_key)', 2),
]);

$tempColumns = $catalog->executeSchemaPragmaCursor('PRAGMA table_info(wp_options)');
$siteIndex = $catalog->executeSchemaPragmaCursor('PRAGMA index_info(wp_sitemeta_key)');

echo json_encode([
    'applicationUse' => 'Iterate copied Application PRAGMA table_info/index_info rowsets with SQLite current/next cursor semantics so import code can stream temp wp_options metadata and attached site indexes without ext/sqlite.',
    'wp_options_schema' => $tempColumns->metadata()['schema'],
    'first_wp_options_column' => $tempColumns->current()['name'],
    'second_wp_options_column' => $tempColumns->next()['name'],
    'remaining_wp_options_columns' => array_column($tempColumns->remainingRows(), 'name'),
    'site_index_schema' => $siteIndex->metadata()['schema'],
    'site_index_column' => $siteIndex->current()['name'],
    'site_index_after_next' => $siteIndex->next(),
], JSON_PRETTY_PRINT) . "\n";
