<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempWalSchemaTriggerPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$catalog = new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
        $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE main.wp_option_audit(option_id integer, option_name text, new_value text)', 2),
        $record('trigger', 'options_ai', 'wp_options', 0, "CREATE TRIGGER main.options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, option_name, new_value) VALUES(new.option_id, new.option_name, new.option_value); END", 3),
    ],
    [
        $record('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer, option_name text, option_value text)', 4),
        $record('table', 'wp_option_audit', 'wp_option_audit', 11, 'CREATE TEMP TABLE wp_option_audit(option_id integer, option_name text, new_value text)', 5),
        $record('trigger', 'options_ai', 'wp_options', 0, "CREATE TEMP TRIGGER options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, option_name, new_value) VALUES(new.option_id, new.option_name, new.option_value); END", 6),
    ],
);
$catalog->attach('site', '/srv/wp/site.sqlite', [
    $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text)', 7),
    $record('table', 'wp_option_audit', 'wp_option_audit', 21, 'CREATE TABLE site.wp_option_audit(blog_id integer, option_name text, new_value text)', 8),
    $record('trigger', 'site_options_ai', 'wp_options', 0, "CREATE TRIGGER site.site_options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(blog_id, option_name, new_value) VALUES(new.blog_id, new.option_name, new.option_value); END", 9),
]);

$plan = SQLiteAttachTempWalSchemaTriggerPlan::triggerCacheRepreparePlan(
    $catalog,
    [
        ['name' => 'options_ai', 'source' => 'temp', 'active' => true],
        ['name' => 'main.options_ai', 'source' => 'main'],
        ['name' => 'site.site_options_ai', 'source' => 'site'],
    ],
    [
        'temp' => [
            $record('table', 'wp_options', 'wp_options', 30, 'CREATE TEMP TABLE wp_options(option_id integer, option_name text, option_value text, expires integer)', 10),
            $record('table', 'wp_option_audit', 'wp_option_audit', 11, 'CREATE TEMP TABLE wp_option_audit(option_id integer, option_name text, new_value text, source text)', 11),
            $record('trigger', 'options_ai', 'wp_options', 0, "CREATE TEMP TRIGGER options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(option_id, option_name, new_value, source) VALUES(new.option_id, new.option_name, new.option_value, 'temp'); END", 12),
        ],
        'site' => [
            $record('table', 'wp_options', 'wp_options', 40, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text, migrated integer)', 13),
            $record('table', 'wp_option_audit', 'wp_option_audit', 21, 'CREATE TABLE site.wp_option_audit(blog_id integer, option_name text, new_value text, source text)', 14),
            $record('trigger', 'site_options_ai', 'wp_options', 0, "CREATE TRIGGER site.site_options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(blog_id, option_name, new_value, source) VALUES(new.blog_id, new.option_name, new.option_value, 'site'); END", 15),
        ],
    ],
    [
        'temp' => ['schema_cookie' => 3],
        'main' => ['schema_cookie' => 12, 'wal_schema_cookie' => 13],
        'site' => ['schema_cookie' => 8, 'wal_frames' => [['page' => 1, 'schema_cookie' => 9, 'commit' => true]]],
    ],
);

$summary = [
    'operation' => $plan['operation'],
    'status' => $plan['status'],
    'reprepare_triggers' => $plan['reprepare_triggers'],
    'active_current_snapshot_triggers' => $plan['active_current_snapshot_triggers'],
    'reset_schema_triggers' => $plan['reset_schema_triggers'],
    'next_step_schema_triggers' => $plan['next_step_schema_triggers'],
    'stable_triggers' => $plan['stable_triggers'],
    'temp_target_after_columns' => $plan['triggers']['options_ai']['after']['target']['columns'],
    'site_target_after_columns' => $plan['triggers']['site.site_options_ai']['after']['target']['columns'],
    'wal_schema_cookie_sources' => $plan['wal_schema_cookie_sources'],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['status'] === 'trigger_cache_expired');
    assert($summary['active_current_snapshot_triggers'] === ['options_ai']);
    assert($summary['reset_schema_triggers'] === ['options_ai']);
    assert($summary['next_step_schema_triggers'] === ['site.site_options_ai']);
    assert($summary['stable_triggers'] === ['main.options_ai']);
    assert($summary['temp_target_after_columns'] === ['option_id', 'option_name', 'option_value', 'expires']);
    assert($summary['site_target_after_columns'] === ['blog_id', 'option_name', 'option_value', 'migrated']);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
