<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, 1);

$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_name TEXT, option_value TEXT)'),
]);

$cachedMiss = $catalog->resolveTable('wp_sitemeta');
$missStats = $catalog->lookupCacheStats();

$catalog->executeAttachDetachSql("ATTACH '/srv/wp-network.sqlite' AS network", static fn (): array => [
    $record('table', 'wp_sitemeta', 'wp_sitemeta', 12, 'CREATE TABLE wp_sitemeta(meta_key TEXT, meta_value TEXT)'),
    $record('index', 'wp_sitemeta_key', 'wp_sitemeta', 13, 'CREATE INDEX wp_sitemeta_key ON wp_sitemeta(meta_key)'),
]);
$attached = $catalog->resolveTable('wp_sitemeta');
$attachedStats = $catalog->lookupCacheStats();

$catalog->executeAttachDetachSql('DETACH network');
$afterDetach = $catalog->resolveTable('wp_sitemeta');

echo json_encode([
    'cached_miss_before_attach' => $cachedMiss,
    'miss_cache_entries_before_attach' => $missStats['entries'],
    'generation_after_attach' => $attachedStats['generation'],
    'cache_entries_after_attach_lookup' => $attachedStats['entries'],
    'attached_schema' => $attached['schema'],
    'attached_rootpage' => $attached['record']->rootPage,
    'after_detach' => $afterDetach,
    'generation_after_detach' => $catalog->schemaGeneration(),
    'database_list' => $catalog->databaseList(),
], JSON_PRETTY_PRINT) . "\n";
