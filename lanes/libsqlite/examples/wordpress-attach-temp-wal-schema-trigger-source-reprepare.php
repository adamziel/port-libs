<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempWalSchemaTriggerPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$current = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text)', 1),
    $record('table', 'wp_audit', 'wp_audit', 3, 'CREATE TABLE main.wp_audit(option_id integer, option_name text, source text)', 2),
    $record('trigger', 'options_ai', 'wp_options', 0, "CREATE TRIGGER main.options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_audit(option_id, option_name, source) VALUES(new.option_id, new.option_name, 'main'); END", 3),
], [
    $record('table', 'wp_audit', 'wp_audit', 11, 'CREATE TEMP TABLE wp_audit(option_id integer, option_name text, source text)', 4),
]);
$current->attach('site', '/srv/wp/site.sqlite', [
    $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text)', 5),
    $record('table', 'wp_audit', 'wp_audit', 21, 'CREATE TABLE site.wp_audit(blog_id integer, option_name text, source text)', 6),
    $record('trigger', 'site_options_ai', 'wp_options', 0, "CREATE TRIGGER site.site_options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_audit(blog_id, option_name, source) VALUES(new.blog_id, new.option_name, 'site'); END", 7),
]);

$next = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, migrated integer)', 1),
    $record('table', 'wp_audit', 'wp_audit', 3, 'CREATE TABLE main.wp_audit(option_id integer, option_name text, source text, migrated integer)', 2),
    $record('trigger', 'options_ai', 'wp_options', 0, "CREATE TRIGGER main.options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_audit(option_id, option_name, source, migrated) VALUES(new.option_id, new.option_name, 'main-next', 1); END", 3),
], [
    $record('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer, option_name text, option_value text, expires integer)', 4),
    $record('table', 'wp_audit', 'wp_audit', 11, 'CREATE TEMP TABLE wp_audit(option_id integer, option_name text, source text, expires integer)', 5),
    $record('trigger', 'options_ai', 'wp_options', 0, "CREATE TEMP TRIGGER options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_audit(option_id, option_name, source) VALUES(new.option_id, new.option_name, 'temp-shadow'); END", 6),
]);
$next->attach('site', '/srv/wp/site.sqlite', [
    $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text, migrated integer)', 7),
    $record('table', 'wp_audit', 'wp_audit', 21, 'CREATE TABLE site.wp_audit(blog_id integer, option_name text, source text, migrated integer)', 8),
    $record('trigger', 'site_options_ai', 'wp_options', 0, "CREATE TRIGGER site.site_options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_audit(blog_id, option_name, source, migrated) VALUES(new.blog_id, new.option_name, 'site-next', 1); END", 9),
]);

$plan = SQLiteAttachTempWalSchemaTriggerPlan::triggerSourceRepreparePlan(
    $current,
    $next,
    [
        ['name' => 'options_ai', 'active' => true],
        ['name' => 'main.options_ai'],
        ['name' => 'site.site_options_ai', 'active' => true],
    ],
    [
        'main' => ['schema_cookie' => 20, 'wal_schema_cookie' => 22],
        'temp' => ['schema_cookie' => 4],
        'site' => ['schema_cookie' => 8, 'wal_frames' => [['page' => 1, 'schema_cookie' => 9, 'commit' => true]]],
    ],
);

echo json_encode([
    'status' => $plan['status'],
    'reprepare_triggers' => $plan['reprepare_triggers'],
    'active_current_snapshot_triggers' => $plan['active_current_snapshot_triggers'],
    'next_step_schema_triggers' => $plan['next_step_schema_triggers'],
    'changed_schemas' => $plan['changed_schemas'],
    'wal_schemas' => $plan['wal_schemas'],
    'temp_shadow_next_schema' => $plan['triggers']['options_ai']['next']['triggerSchema'],
    'main_qualified_next_schema' => $plan['triggers']['main.options_ai']['next']['triggerSchema'],
    'site_cookie_next' => $plan['schema_cookies_next']['site'],
], JSON_PRETTY_PRINT) . "\n";
