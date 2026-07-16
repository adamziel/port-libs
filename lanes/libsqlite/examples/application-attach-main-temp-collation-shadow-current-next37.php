<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachMainTempCollationShadowPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, 1);

$catalog = new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_name TEXT, option_value TEXT)'),
        $record('index', 'main_options_name_nocase', 'wp_options', 3, 'CREATE INDEX main_options_name_nocase ON wp_options(option_name COLLATE NOCASE)'),
    ],
    [
        $record('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_name TEXT, option_value TEXT)'),
        $record('index', 'temp_options_name_locale', 'wp_options', 11, 'CREATE INDEX temp_options_name_locale ON wp_options(option_name COLLATE wp_locale)'),
    ],
);

$catalog->attach('site', '/srv/www/site.sqlite', [
    $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE wp_options(option_name TEXT, option_value TEXT)'),
    $record('index', 'site_options_name_slug', 'wp_options', 21, 'CREATE INDEX site_options_name_slug ON wp_options(option_name COLLATE wp_slug)'),
]);

$preview = [
    'temp_without_locale' => SQLiteAttachMainTempCollationShadowPlan::plan($catalog, 'wp_options'),
    'temp_with_locale' => SQLiteAttachMainTempCollationShadowPlan::plan($catalog, 'wp_options', ['wp_locale']),
    'main_qualified' => SQLiteAttachMainTempCollationShadowPlan::plan($catalog, 'main.wp_options'),
    'summary' => SQLiteAttachMainTempCollationShadowPlan::shadowSummary(
        SQLiteAttachMainTempCollationShadowPlan::plan($catalog, 'wp_options'),
    ),
];

if (($argv[1] ?? '') === '--self-test') {
    assert($preview['temp_without_locale']['status'] === 'current-source-collation-blocked-by-shadow');
    assert($preview['temp_without_locale']['current_schema'] === 'temp');
    assert($preview['temp_without_locale']['missing_collations'] === ['WP_LOCALE']);
    assert($preview['temp_without_locale']['blocked_shadowed_usable_indexes'] === ['main.main_options_name_nocase']);
    assert($preview['temp_with_locale']['status'] === 'ok');
    assert($preview['main_qualified']['current_schema'] === 'main');
    echo "application-attach-main-temp-collation-shadow-current-next37 self-test passed\n";
    return;
}

echo json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
