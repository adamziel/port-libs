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

$loader = static function (string $file, string $schema) use ($record): array {
    if ($schema === 'site') {
        return [
            $record('table', 'wp_sitemeta', 'wp_sitemeta', 10, 'CREATE TABLE wp_sitemeta(meta_key TEXT, meta_value TEXT)'),
            $record('index', 'wp_sitemeta_key', 'wp_sitemeta', 11, 'CREATE INDEX wp_sitemeta_key ON wp_sitemeta(meta_key)'),
        ];
    }

    return [
        $record('table', 'network_options', 'network_options', 20, 'CREATE TABLE network_options(option_name TEXT, option_value TEXT)'),
    ];
};

$siteAttach = $catalog->executeAttachDetachSql("ATTACH DATABASE '/srv/www/site.sqlite' AS site", $loader);
$archiveAttach = $catalog->executeAttachDetachSql("ATTACH '/srv/www/archive.sqlite' AS archive", $loader);
$siteMeta = $catalog->executeSchemaPragma('PRAGMA table_info(wp_sitemeta)');
$catalog->executeAttachDetachSql('DETACH DATABASE site');

echo json_encode([
    'site_attach_schema' => $siteAttach['schema'],
    'archive_attach_seq' => $archiveAttach['database_list'][3]['seq'],
    'site_meta_schema_before_detach' => $siteMeta['schema'],
    'site_meta_columns_before_detach' => array_column($siteMeta['rows'], 'name'),
    'default_wp_options_schema_after_attach' => $catalog->resolveTable('wp_options')['schema'],
    'database_list_after_site_detach' => $catalog->databaseList(),
    'site_meta_after_detach_rows' => $catalog->executeSchemaPragma('PRAGMA table_info(wp_sitemeta)')['rows'],
], JSON_PRETTY_PRINT) . "\n";
