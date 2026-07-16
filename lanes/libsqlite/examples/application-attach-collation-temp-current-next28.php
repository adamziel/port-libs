<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachCollationTempCurrentPlan;
use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, 1);

$catalog = new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_name TEXT, option_value TEXT)'),
        $record('index', 'wp_options_name_nocase', 'wp_options', 3, 'CREATE INDEX wp_options_name_nocase ON wp_options(option_name COLLATE NOCASE)'),
    ],
    [
        $record('table', 'wp_options', 'wp_options', 4, 'CREATE TEMP TABLE wp_options(option_name TEXT, option_value TEXT)'),
        $record('index', 'temp_options_locale', 'wp_options', 5, 'CREATE INDEX temp_options_locale ON wp_options(option_name COLLATE wp_locale)'),
    ],
);

$catalog->executeAttachDetachSql("ATTACH '/srv/wp/site.sqlite' AS site", static fn (): array => [
    $record('table', 'wp_sitemeta', 'wp_sitemeta', 10, 'CREATE TABLE wp_sitemeta(meta_key TEXT, meta_value TEXT)'),
    $record('index', 'site_meta_slug', 'wp_sitemeta', 11, 'CREATE INDEX site_meta_slug ON wp_sitemeta(meta_key COLLATE wp_slug)'),
]);

$preview = [
    'temp_options_without_locale' => SQLiteAttachCollationTempCurrentPlan::plan($catalog, 'wp_options'),
    'temp_options_with_locale' => SQLiteAttachCollationTempCurrentPlan::plan($catalog, 'wp_options', ['wp_locale']),
    'attached_sitemeta_with_slug' => SQLiteAttachCollationTempCurrentPlan::plan($catalog, 'wp_sitemeta', ['wp_slug']),
];

if (($argv[1] ?? '') === '--self-test') {
    assert($preview['temp_options_without_locale']['status'] === 'missing-collation');
    assert($preview['temp_options_without_locale']['current_schema'] === 'temp');
    assert($preview['temp_options_without_locale']['missing_collations'] === ['WP_LOCALE']);
    assert($preview['temp_options_with_locale']['status'] === 'ok');
    assert($preview['attached_sitemeta_with_slug']['current_schema'] === 'site');
    assert($preview['attached_sitemeta_with_slug']['usable_indexes'] === ['site_meta_slug']);
    echo "application-attach-collation-temp-current-next28 self-test passed\n";
    return;
}

echo json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
