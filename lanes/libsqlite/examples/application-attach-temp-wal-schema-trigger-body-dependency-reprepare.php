<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempWalSchemaTriggerPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$current = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text)', 1),
    $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE main.wp_option_audit(option_id integer, option_name text, source text)', 2),
    $record('trigger', 'temp_options_ai', 'wp_options', 0, "CREATE TEMP TRIGGER temp_options_ai AFTER INSERT ON main.wp_options BEGIN INSERT INTO wp_option_audit(option_id, option_name, source) VALUES(new.option_id, new.option_name, 'current'); END", 3),
], []);
$current->attach('site', '/srv/wp/site.sqlite', [
    $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text)', 4),
    $record('table', 'wp_option_audit', 'wp_option_audit', 21, 'CREATE TABLE site.wp_option_audit(blog_id integer, option_name text, source text)', 5),
    $record('trigger', 'site_options_ai', 'wp_options', 0, "CREATE TRIGGER site.site_options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(blog_id, option_name, source) VALUES(new.blog_id, new.option_name, 'site'); END", 6),
]);

$next = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text)', 1),
    $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE main.wp_option_audit(option_id integer, option_name text, source text)', 2),
    $record('trigger', 'temp_options_ai', 'wp_options', 0, "CREATE TEMP TRIGGER temp_options_ai AFTER INSERT ON main.wp_options BEGIN INSERT INTO wp_option_audit(option_id, option_name, source) VALUES(new.option_id, new.option_name, 'current'); END", 3),
], [
    $record('table', 'wp_option_audit', 'wp_option_audit', 40, 'CREATE TEMP TABLE wp_option_audit(option_id integer, option_name text, source text, expires integer)', 4),
]);
$next->attach('site', '/srv/wp/site.sqlite', [
    $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text)', 5),
    $record('table', 'wp_option_audit', 'wp_option_audit', 21, 'CREATE TABLE site.wp_option_audit(blog_id integer, option_name text, source text)', 6),
    $record('trigger', 'site_options_ai', 'wp_options', 0, "CREATE TRIGGER site.site_options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(blog_id, option_name, source) VALUES(new.blog_id, new.option_name, 'site'); END", 7),
]);

$plan = SQLiteAttachTempWalSchemaTriggerPlan::triggerBodyDependencyRepreparePlan(
    $current,
    $next,
    [
        ['name' => 'temp_options_ai', 'active' => true],
        ['name' => 'site.site_options_ai'],
    ],
    [
        'main' => ['schema_cookie' => 12, 'wal_schema_cookie' => 13],
        'temp' => ['schema_cookie' => 4],
        'site' => ['schema_cookie' => 8],
    ],
);

$summary = [
    'status' => $plan['status'],
    'reprepare_triggers' => $plan['reprepare_triggers'],
    'body_dependency_expired_triggers' => $plan['body_dependency_expired_triggers'],
    'active_current_snapshot_triggers' => $plan['active_current_snapshot_triggers'],
    'changed_schemas' => $plan['changed_schemas'],
    'temp_audit_current_schema' => $plan['triggers']['temp_options_ai']['body_dependency_resolution']['current'][0]['resolved_schema'],
    'temp_audit_next_schema' => $plan['triggers']['temp_options_ai']['body_dependency_resolution']['next'][0]['resolved_schema'],
    'site_trigger_action' => $plan['triggers']['site.site_options_ai']['next_step_action'],
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['status'] === 'trigger_body_dependency_expired');
    assert($summary['reprepare_triggers'] === ['temp_options_ai']);
    assert($summary['body_dependency_expired_triggers'] === ['temp_options_ai']);
    assert($summary['active_current_snapshot_triggers'] === ['temp_options_ai']);
    assert($summary['temp_audit_current_schema'] === 'main');
    assert($summary['temp_audit_next_schema'] === 'temp');
    assert($summary['site_trigger_action'] === 'reuse_prepared_trigger');
    echo "application-attach-temp-wal-schema-trigger-body-dependency-reprepare self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
