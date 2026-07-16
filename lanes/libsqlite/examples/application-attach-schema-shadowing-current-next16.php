<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, 1);

$catalog = new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)'),
    ],
    [
        $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_name TEXT, option_value TEXT)'),
    ],
);
$catalog->attach('site', '/srv/site.sqlite', [
    $record('table', 'wp_options', 'wp_options', 6, 'CREATE TABLE wp_options(blog_id INTEGER, option_name TEXT)'),
    $record('table', 'wp_sitemeta', 'wp_sitemeta', 7, 'CREATE TABLE wp_sitemeta(meta_key TEXT, meta_value TEXT)'),
]);

echo json_encode([
    'unqualified_wp_options_schema' => $catalog->resolveTable('wp_options')['schema'],
    'unqualified_sqlite_schema_schema' => $catalog->resolveTable('sqlite_schema')['schema'],
    'temp_catalog_alias_schema' => $catalog->resolveTable('sqlite_temp_schema')['schema'],
    'site_catalog_alias_schema' => $catalog->resolveTable('site.sqlite_master')['schema'],
    'site_catalog_alias_root' => $catalog->resolveTable('site.sqlite_schema')['record']->rootPage,
    'database_list' => $catalog->databaseList(),
], JSON_PRETTY_PRINT) . "\n";
