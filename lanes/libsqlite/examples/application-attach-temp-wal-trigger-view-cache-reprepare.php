<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempWalSchemaTriggerPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$current = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
    $record('view', 'active_options', 'active_options', 0, "CREATE VIEW main.active_options AS SELECT option_id, option_name, option_value FROM main.wp_options WHERE autoload = 'yes'", 2),
    $record('trigger', 'active_options_io', 'active_options', 0, "CREATE TRIGGER main.active_options_io INSTEAD OF UPDATE ON active_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE option_id = old.option_id; END", 3),
]);
$current->attach('site', '/srv/wp/site.sqlite', [
    $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text, autoload text)', 4),
    $record('view', 'site_active_options', 'site_active_options', 0, "CREATE VIEW site.site_active_options AS SELECT blog_id, option_name, option_value FROM site.wp_options WHERE autoload = 'yes'", 5),
    $record('trigger', 'site_active_options_io', 'site_active_options', 0, "CREATE TRIGGER site.site_active_options_io INSTEAD OF UPDATE ON site_active_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE blog_id = old.blog_id; END", 6),
]);

$next = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text, migrated integer)', 1),
    $record('view', 'active_options', 'active_options', 0, "CREATE VIEW main.active_options AS SELECT option_id, option_name, option_value FROM main.wp_options WHERE autoload IN ('yes','auto-on')", 2),
    $record('trigger', 'active_options_io', 'active_options', 0, "CREATE TRIGGER main.active_options_io INSTEAD OF UPDATE ON active_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE option_id = old.option_id; END", 3),
], [
    $record('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer, option_name text, option_value text, expires integer)', 4),
    $record('view', 'active_options', 'active_options', 0, 'CREATE TEMP VIEW active_options AS SELECT option_id, option_name, option_value FROM temp.wp_options WHERE expires > 0', 5),
    $record('trigger', 'active_options_io', 'active_options', 0, "CREATE TEMP TRIGGER active_options_io INSTEAD OF UPDATE ON active_options BEGIN UPDATE temp.wp_options SET option_value = new.option_value WHERE option_id = old.option_id; END", 6),
]);
$next->attach('site', '/srv/wp/site.sqlite', [
    $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text, autoload text, migrated integer)', 7),
    $record('view', 'site_active_options', 'site_active_options', 0, "CREATE VIEW site.site_active_options AS SELECT blog_id, option_name, option_value FROM site.wp_options WHERE autoload IN ('yes','network')", 8),
    $record('trigger', 'site_active_options_io', 'site_active_options', 0, "CREATE TRIGGER site.site_active_options_io INSTEAD OF UPDATE ON site_active_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE blog_id = old.blog_id; END", 9),
]);

$plan = SQLiteAttachTempWalSchemaTriggerPlan::triggerViewCacheRepreparePlan(
    $current,
    $next,
    [
        ['name' => 'active_options_io', 'active' => true],
        ['name' => 'main.active_options_io'],
        ['name' => 'site.site_active_options_io', 'active' => true],
    ],
    [
        'main' => ['schema_cookie' => 31, 'wal_schema_cookie' => 32],
        'temp' => ['schema_cookie' => 8],
        'site' => ['schema_cookie' => 11, 'wal_frames' => [['page' => 1, 'schema_cookie' => 12, 'commit' => true]]],
    ],
);

$summary = [
    'scenario' => 'application-attach-temp-wal-trigger-view-cache-reprepare',
    'status' => $plan['status'],
    'reprepareTriggers' => $plan['reprepare_triggers'],
    'activeCurrentSnapshotTriggers' => $plan['active_current_snapshot_triggers'],
    'viewExpiredTriggers' => $plan['view_cache_expired_triggers'],
    'unqualifiedCurrentViewSchema' => $plan['view_caches']['active_options_io']['current']['schema'],
    'unqualifiedNextViewSchema' => $plan['view_caches']['active_options_io']['next']['schema'],
    'siteNextSchemaCookie' => $plan['schema_cookies_next']['site'],
    'applicationUse' => 'Copied Application SQLite imports can keep active INSTEAD OF triggers on current view definitions until reset while temp schema shadows and WAL page-one schema cookies force the next trigger step to reprepare.',
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    assert($summary['status'] === 'trigger_view_cache_expired');
    assert($summary['unqualifiedCurrentViewSchema'] === 'main');
    assert($summary['unqualifiedNextViewSchema'] === 'temp');
    assert($summary['siteNextSchemaCookie'] === 12);
    echo "application-attach-temp-wal-trigger-view-cache-reprepare self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
