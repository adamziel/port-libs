<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, 1);

$catalog = new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_options', 'wp_options', 2, "CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, autoload TEXT DEFAULT 'yes')"),
        $record('index', 'wp_options_name_main', 'wp_options', 3, 'CREATE INDEX wp_options_name_main ON wp_options(option_name)'),
    ],
    [
        $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_name TEXT, option_value TEXT, transient_timeout INTEGER)'),
        $record('index', 'wp_options_name_temp', 'wp_options', 5, 'CREATE INDEX wp_options_name_temp ON wp_options(option_name)'),
    ],
);
$catalog->attach('site', '/srv/www/site.sqlite', [
    $record('table', 'wp_sitemeta', 'wp_sitemeta', 6, 'CREATE TABLE wp_sitemeta(meta_id INTEGER PRIMARY KEY, meta_key TEXT, meta_value TEXT)'),
    $record('index', 'wp_sitemeta_key', 'wp_sitemeta', 7, 'CREATE INDEX wp_sitemeta_key ON wp_sitemeta(meta_key)'),
]);

$tempInfo = $catalog->executeSchemaPragma('PRAGMA table_info(wp_options)');
$mainInfo = $catalog->executeSchemaPragma('PRAGMA main.table_info(wp_options)');
$siteIndex = $catalog->executeSchemaPragma('PRAGMA index_info(wp_sitemeta_key)');

echo json_encode([
    'applicationUse' => 'Preview copied wp_options PRAGMA schema introspection using SQLite current-source resolution so temp import tables shadow main tables while attached site metadata remains discoverable without ext/sqlite.',
    'unqualified_wp_options_schema' => $tempInfo['schema'],
    'unqualified_wp_options_columns' => array_column($tempInfo['rows'], 'name'),
    'main_wp_options_schema' => $mainInfo['schema'],
    'main_wp_options_columns' => array_column($mainInfo['rows'], 'name'),
    'site_index_schema' => $siteIndex['schema'],
    'site_index_columns' => array_column($siteIndex['rows'], 'name'),
], JSON_PRETTY_PRINT) . "\n";
