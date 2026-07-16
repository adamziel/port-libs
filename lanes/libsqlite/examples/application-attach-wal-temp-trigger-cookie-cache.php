<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempWalSchemaTriggerPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
    $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE main.wp_option_audit(option_id integer, option_name text, new_value text)', 2),
    $record('trigger', 'options_au', 'wp_options', 0, "CREATE TRIGGER main.options_au AFTER UPDATE ON wp_options BEGIN UPDATE wp_option_audit SET new_value = new.option_value WHERE option_id = old.option_id; SELECT option_id FROM wp_option_audit WHERE option_id = new.option_id; END", 3),
], [
    $record('table', 'wp_option_audit', 'wp_option_audit', 10, 'CREATE TEMP TABLE wp_option_audit(option_id integer, option_name text, new_value text)', 4),
    $record('trigger', 'temp_options_au', 'wp_options', 0, "CREATE TEMP TRIGGER temp_options_au AFTER UPDATE ON main.wp_options BEGIN INSERT INTO wp_option_audit(option_id, option_name, new_value) VALUES(new.option_id, new.option_name, new.option_value); INSERT INTO main.wp_option_audit(option_id, option_name, new_value) VALUES(new.option_id, new.option_name, new.option_value); END", 5),
]);
$catalog->attach('site', '/srv/wp/site.sqlite', [
    $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text)', 6),
    $record('table', 'wp_option_audit', 'wp_option_audit', 21, 'CREATE TABLE site.wp_option_audit(blog_id integer, option_name text, new_value text)', 7),
    $record('trigger', 'site_options_au', 'wp_options', 0, "CREATE TRIGGER site.site_options_au AFTER UPDATE ON wp_options BEGIN UPDATE wp_option_audit SET new_value = new.option_value WHERE blog_id = old.blog_id; END", 8),
]);

$plan = SQLiteAttachTempWalSchemaTriggerPlan::walTriggerCookieCachePlan(
    $catalog,
    [
        ['name' => 'main.options_au', 'source' => 'main', 'active' => true],
        ['name' => 'temp_options_au', 'source' => 'temp'],
        ['name' => 'site.site_options_au', 'source' => 'site'],
    ],
    [],
    [
        'main' => ['schema_cookie' => 12, 'wal_frames' => [['page' => 1, 'schema_cookie' => 13, 'commit' => true]]],
        'temp' => ['schema_cookie' => 4, 'wal_schema_cookie' => 5],
        'site' => ['schema_cookie' => 8],
    ],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-trigger-cache-current-source-next89');
    assert($plan['active_current_snapshot_triggers'] === ['main.options_au']);
    assert($plan['next_step_schema_triggers'] === ['temp_options_au']);
    assert($plan['stable_triggers'] === ['site.site_options_au']);
    assert($plan['triggers']['main.options_au']['current_step_result'] === 'SQLITE_OK');
    assert($plan['triggers']['temp_options_au']['current_step_result'] === 'SQLITE_SCHEMA');
    echo "application-attach-wal-temp-trigger-cookie-cache self-test passed\n";
    return;
}

echo json_encode([
    'operation' => $plan['operation'],
    'status' => $plan['status'],
    'changed_schemas' => $plan['changed_schemas'],
    'current_source_triggers' => $plan['active_current_snapshot_triggers'],
    'next_step_schema_triggers' => $plan['next_step_schema_triggers'],
    'stable_triggers' => $plan['stable_triggers'],
    'schema_cookies_current' => $plan['schema_cookies_current'],
    'schema_cookies_next' => $plan['schema_cookies_next'],
    'main_trigger_next_action' => $plan['triggers']['main.options_au']['next_step_action'],
    'temp_trigger_cookie_schemas' => $plan['triggers']['temp_options_au']['cookie_changed_schemas'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
