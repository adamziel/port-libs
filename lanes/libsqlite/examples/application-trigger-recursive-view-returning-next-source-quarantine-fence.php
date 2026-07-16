<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'source' => 'seed'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes', 'source' => 'seed'],
];
$view = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-182-current',
    'trigger' => 'app_setting_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-182-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_retry',
    'audit_label' => 'current-recursive-trigger-drain-182',
];
$nextView = $view;
$nextView['source'] = 'main@view-cookie-182-next';
$nextView['trigger_source'] = 'main@trigger-cookie-182-next';
$nextView['columns'] = ['import_id', 'name', 'value', 'load_policy_flag', 'origin'];
$nextView['mapping']['origin'] = 'source';
$nextView['recursive_suffix'] = '_next_retry';
$nextView['audit_label'] = 'next-recursive-trigger-drain-182';

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNextSourceQuarantineFence(
    $rows,
    [
        ['import_id' => 10, 'name' => 'module_seed', 'value' => 'enabled', 'load_policy_flag' => 'yes', 'spawn_child' => true],
        ['import_id' => 12, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
    ],
    [
        ['import_id' => 20, 'name' => 'routing_rules', 'value' => 'cached', 'load_policy_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => true],
        ['import_id' => 21, 'name' => 'landing_url', 'value' => 'https://next-landing_url.test', 'load_policy_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => false],
    ],
    $view,
    $nextView,
    ['new.key_name', ['expr' => 'new.key_value', 'as' => 'value'], ['expr' => 'trigger_source', 'as' => 'trigger_cookie']],
    [
        'key' => 'key_name',
        'savepoint' => 'app_recursive_view_182',
        'max_depth' => 2,
        'page_size' => 2,
        'drained_current_pages' => 2,
        'savepoint_action' => 'release',
        'current_source_epoch' => 12,
        'restart_cursor' => 'app-recursive-view-returning-restart-182',
        'snapshot_token' => 'app.recursive.view.returning.snapshot.182',
        'expected_snapshot_token' => 'app.recursive.view.returning.snapshot.182',
        'current_schema_cookie' => 182,
        'expected_current_schema_cookie' => 182,
        'current_source_generation' => 'app.recursive.view.current.182',
        'expected_current_source_generation' => 'app.recursive.view.current.182',
        'trigger_source_generation' => 'app.recursive.trigger.current.182',
        'expected_trigger_source_generation' => 'app.recursive.trigger.current.182',
        'returning_cursor_generation' => 'app.recursive.returning.cursor.182',
    ],
);

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if (($summary['status_next182'] ?? null) !== 'trigger-recursive-view-returning-current-source-generation-released-next182') {
        fwrite(STDERR, "unexpected recursive view RETURNING next182 status\n");
        exit(1);
    }
    if (($summary['returning_generation_plan_next182']['decision'] ?? null) !== 'publish-current-then-next-generation') {
        fwrite(STDERR, "recursive view RETURNING next182 generation fence did not publish current then next rows\n");
        exit(1);
    }
    if (($summary['statement_returning_row_count_next182'] ?? null) !== 8) {
        fwrite(STDERR, "recursive view RETURNING next182 statement row count changed\n");
        exit(1);
    }
    echo "application-trigger-recursive-view-returning-current-source-next182 self-test passed\n";
}

return [
    'scenario' => 'application-trigger-recursive-view-returning-current-source-next182',
    'applicationUse' => 'Copied app_settings imports through an INSTEAD OF recursive view trigger keep RETURNING rows tied to the current view-source and trigger-source generation before queued next-source rows are visible.',
    'dependencyClosure' => 'no new support component needed; reuses native PHP recursive view RETURNING current-source generation and trigger-cookie modeling',
    'summary' => [
        'status' => $summary['status_next182'],
        'decision' => $summary['returning_generation_plan_next182']['decision'],
        'visibleRows' => $summary['statement_returning_row_count_next182'],
        'currentRows' => $summary['current_returning_row_count_next182'],
        'nextRows' => $summary['next_returning_row_count_next182'],
        'sourceOrder' => $summary['returning_source_order_next182'],
    ],
];
