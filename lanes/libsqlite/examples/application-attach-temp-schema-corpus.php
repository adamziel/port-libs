<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, 1);

$catalog = new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, autoload TEXT)'),
    ],
    [
        $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_name TEXT, option_value TEXT)'),
    ],
);
$catalog->attach('site', '/srv/www/site-meta.sqlite', [
    $record('table', 'wp_sitemeta', 'wp_sitemeta', 7, 'CREATE TABLE wp_sitemeta(meta_key TEXT, meta_value TEXT)'),
]);

$defaultOptions = $catalog->resolveTable('wp_options');
$mainOptions = $catalog->resolveTable('main.wp_options');
$siteMeta = $catalog->resolveTable('site.wp_sitemeta');

echo json_encode([
    'default_wp_options_schema' => $defaultOptions['schema'],
    'default_wp_options_root' => $defaultOptions['record']->rootPage,
    'main_wp_options_root' => $mainOptions['record']->rootPage,
    'site_meta_schema' => $siteMeta['schema'],
    'database_list' => $catalog->databaseList(),
], JSON_PRETTY_PRINT) . "\n";
