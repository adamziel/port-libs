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
], [
    $record('table', 'wp_audit', 'wp_audit', 10, 'CREATE TEMP TABLE wp_audit(option_id integer, option_name text, source text)', 3),
    $record('trigger', 'temp_bridge_ai', 'wp_options', 0, "CREATE TEMP TRIGGER temp_bridge_ai AFTER INSERT ON main.wp_options BEGIN INSERT INTO wp_audit(option_id, option_name, source) VALUES(new.option_id, new.option_name, 'temp'); INSERT INTO site.wp_audit(blog_id, option_name, source) VALUES(new.option_id, new.option_name, 'site'); END", 4),
]);
$current->attach('site', '/srv/wp/site.sqlite', [
    $record('table', 'wp_audit', 'wp_audit', 20, 'CREATE TABLE site.wp_audit(blog_id integer, option_name text, source text)', 5),
]);

$next = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, migrated integer)', 1),
    $record('table', 'wp_audit', 'wp_audit', 3, 'CREATE TABLE main.wp_audit(option_id integer, option_name text, source text, migrated integer)', 2),
], [
    $record('trigger', 'temp_bridge_ai', 'wp_options', 0, "CREATE TEMP TRIGGER temp_bridge_ai AFTER INSERT ON main.wp_options BEGIN INSERT INTO wp_audit(option_id, option_name, source) VALUES(new.option_id, new.option_name, 'main-after-temp-drop'); INSERT INTO site.wp_audit(blog_id, option_name, source) VALUES(new.option_id, new.option_name, 'site'); END", 3),
]);
$next->attach('site', '/srv/wp/site.sqlite', [
    $record('table', 'wp_audit', 'wp_audit', 20, 'CREATE TABLE site.wp_audit(blog_id integer, option_name text, source text)', 4),
]);

$plan = SQLiteAttachTempWalSchemaTriggerPlan::currentSourceNext95(
    $current,
    $next,
    [['name' => 'temp_bridge_ai', 'active' => true]],
    [
        'main' => ['schema_cookie' => 30, 'wal_schema_cookie' => 31],
        'temp' => ['schema_cookie' => 7],
        'site' => ['schema_cookie' => 12, 'wal_frames' => [['page' => 1, 'schema_cookie' => 13, 'commit' => true]]],
    ],
);

$output = [
    'scenario' => 'wordpress-attach-temp-wal-schema-trigger-current-source-next95',
    'wordpressUse' => 'Preview copied WordPress option-import TEMP triggers whose unqualified audit writes move from TEMP to main after schema reload while attached-site audit writes still watch WAL schema cookies, without requiring ext/sqlite.',
    'status' => $plan['status'],
    'reprepareTriggers' => $plan['reprepare_triggers'],
    'activeCurrentSnapshotTriggers' => $plan['active_current_snapshot_triggers'],
    'dependencyMovedTriggers' => $plan['dependency_moved_triggers'],
    'cookieExpiredTriggers' => $plan['cookie_expired_triggers'],
    'currentBodyDependencySchemas' => $plan['triggers']['temp_bridge_ai']['current_body_dependency_schemas'],
    'nextBodyDependencySchemas' => $plan['triggers']['temp_bridge_ai']['next_body_dependency_schemas'],
    'changedSchemas' => $plan['changed_schemas'],
    'mainCookieNext' => $plan['schema_cookies_next']['main'],
    'siteCookieNext' => $plan['schema_cookies_next']['site'],
    'dependency' => in_array('sqlite-temp-trigger-body-dependency-resolution', $plan['dependencies'], true),
];

echo json_encode($output, JSON_PRETTY_PRINT) . "\n";

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv ?? [], true)) {
    if (
        $output['status'] !== 'trigger_current_source_expired'
        || $output['dependencyMovedTriggers'] !== ['temp_bridge_ai']
        || $output['currentBodyDependencySchemas'] !== ['temp', 'site']
        || $output['nextBodyDependencySchemas'] !== ['main', 'site']
        || $output['mainCookieNext'] !== 31
        || $output['siteCookieNext'] !== 13
        || $output['dependency'] !== true
    ) {
        fwrite(STDERR, "wordpress attach temp wal schema trigger current-source next95 smoke failed\n");
        exit(1);
    }

    echo "wordpress-attach-temp-wal-schema-trigger-current-source-next95 self-test passed\n";
}
