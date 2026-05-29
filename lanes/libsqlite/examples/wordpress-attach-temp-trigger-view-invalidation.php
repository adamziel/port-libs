<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempWalSchemaTriggerPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$current = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
    $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE main.wp_option_audit(option_id integer, option_name text, source text)', 2),
], [
    $record('view', 'active_options', 'active_options', 0, "CREATE TEMP VIEW active_options AS SELECT option_id, option_name, option_value FROM wp_options WHERE autoload = 'yes'", 3),
    $record('trigger', 'active_options_io', 'active_options', 0, "CREATE TEMP TRIGGER active_options_io INSTEAD OF UPDATE ON active_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE option_id = old.option_id; INSERT INTO wp_option_audit(option_id, option_name, source) VALUES(new.option_id, new.option_name, 'temp-view'); END", 4),
]);

$next = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
    $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE main.wp_option_audit(option_id integer, option_name text, source text)', 2),
], [
    $record('table', 'wp_options', 'wp_options', 40, 'CREATE TEMP TABLE wp_options(option_id integer, option_name text, option_value text, autoload text, expires integer)', 3),
    $record('view', 'active_options', 'active_options', 0, "CREATE TEMP VIEW active_options AS SELECT option_id, option_name, option_value FROM wp_options WHERE autoload = 'yes'", 4),
    $record('trigger', 'active_options_io', 'active_options', 0, "CREATE TEMP TRIGGER active_options_io INSTEAD OF UPDATE ON active_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE option_id = old.option_id; INSERT INTO wp_option_audit(option_id, option_name, source) VALUES(new.option_id, new.option_name, 'temp-view'); END", 5),
]);

$plan = SQLiteAttachTempWalSchemaTriggerPlan::triggerViewDependencyInvalidationPlan(
    $current,
    $next,
    [['name' => 'active_options_io', 'active' => true]],
    [
        'main' => ['schema_cookie' => 21],
        'temp' => ['schema_cookie' => 6],
    ],
);

$output = [
    'scenario' => 'wordpress attach temp trigger view invalidation view cache reprepare108',
    'wordpressUse' => 'Preview copied wp_options TEMP view imports where the prepared trigger/view SQL text is unchanged, but a TEMP staging table appears and changes the view source from main.wp_options to temp.wp_options.',
    'status' => $plan['status'],
    'requiresReprepare' => $plan['requires_reprepare'],
    'reprepareTriggers' => $plan['reprepare_triggers'],
    'changedSchemas' => $plan['changed_schemas'],
    'currentViewDependency' => $plan['view_caches']['active_options_io']['dependency_resolution']['current'][0],
    'nextViewDependency' => $plan['view_caches']['active_options_io']['dependency_resolution']['next'][0],
    'nextStepAction' => $plan['triggers']['active_options_io']['next_step_action'],
    'dependencies' => $plan['dependencies'],
];

if (($argv[1] ?? '') === '--self-test') {
    if (
        $output['status'] !== 'trigger_view_dependency_expired'
        || $output['currentViewDependency']['resolved_schema'] !== 'main'
        || $output['nextViewDependency']['resolved_schema'] !== 'temp'
        || $output['nextStepAction'] !== 'finish_current_view_dependency_source_then_sqlite_schema_on_reset'
    ) {
        fwrite(STDERR, "wordpress-attach-temp-trigger-view-invalidation self-test failed\n");
        exit(1);
    }

    echo "wordpress-attach-temp-trigger-view-invalidation self-test passed\n";
    exit(0);
}

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
