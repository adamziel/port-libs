<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, 1);

$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)'),
]);

$loader = static fn (string $file, string $schema): array => [
    $record('table', 'wp_sitemeta', 'wp_sitemeta', 10, 'CREATE TABLE wp_sitemeta(meta_key TEXT, meta_value TEXT)'),
    $record('index', 'wp_sitemeta_key', 'wp_sitemeta', 11, 'CREATE INDEX wp_sitemeta_key ON wp_sitemeta(meta_key)'),
];

$before = $catalog->schemaCacheSnapshot();
$attach = $catalog->executeAttachDetachSql("ATTACH '/srv/www/site.sqlite' AS site", $loader);
$afterAttach = $catalog->schemaCacheInvalidation($before);
$preparedSitePlan = $catalog->schemaCacheSnapshot('site');
$catalog->executeAttachDetachSql('DETACH site');
$afterDetach = $catalog->schemaCacheInvalidation($preparedSitePlan);

echo json_encode([
    'applicationUse' => 'Invalidate copied Application import prepared metadata plans when ATTACH/DETACH changes SQLite database_list/search order, so stale wp_sitemeta PRAGMA plans are reprepared without ext/sqlite.',
    'attachGeneration' => $attach['schema_generation'],
    'attachInvalidatedSchemas' => $afterAttach['invalidated_schemas'],
    'sitePlanCurrentAfterDetach' => $afterDetach['current'],
    'detachedSchemas' => $afterDetach['removed_schemas'],
    'databaseListAfterDetach' => $catalog->databaseList(),
    'wpSitemetaFallbackRowsAfterDetach' => $catalog->executeSchemaPragma('PRAGMA table_info(wp_sitemeta)')['rows'],
], JSON_PRETTY_PRINT) . "\n";
