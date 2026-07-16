<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$currentView = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-done_gate-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-done_gate-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    'audit_label' => 'current-recursive-view-trigger-done_gate',
];
$nextView = $currentView;
$nextView['source'] = 'main@view-cookie-done_gate-next';
$nextView['trigger_source'] = 'main@trigger-cookie-done_gate-next';
$nextView['audit_label'] = 'next-recursive-view-trigger-done_gate';

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentSourceDoneGate(
    [
        ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes'],
        ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes'],
    ],
    [
        ['import_id' => 10, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
        ['import_id' => 11, 'name' => 'current_module', 'value' => 'enabled', 'load_policy_flag' => 'no', 'spawn_child' => true],
    ],
    [
        ['import_id' => 20, 'name' => 'landing_url', 'value' => 'https://next.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
        ['import_id' => 21, 'name' => 'next_module', 'value' => 'active', 'load_policy_flag' => 'no', 'spawn_child' => false],
    ],
    $currentView,
    $nextView,
    [
        ['expr' => 'new.key_name', 'as' => 'name'],
        ['expr' => 'event', 'as' => 'event_name'],
        ['expr' => 'depth', 'as' => 'depth_value'],
        ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ],
    [
        'savepoint' => 'app_recursive_view_done_gate',
        'cursor_name' => 'app_recursive_view_returning_cursor_done_gate',
        'checkpoint_name' => 'app_recursive_view_checkpoint_done_gate',
        'page_size' => 3,
    ],
);

if (
    $summary['status'] !== 'trigger-recursive-view-returning-current-source-done-gate-next-exposed'
    || $summary['current_result_code_done_gate'] !== 'SQLITE_DONE'
    || $summary['next_source_exposed_after_current_done_done_gate'] !== true
    || $summary['current_done_plan_done_gate']['decision'] !== 'admit-next-source-after-current-done'
    || count($summary['visible_returning_rows']) !== 10
    || $summary['held_rows'] !== []
) {
    fwrite(STDERR, "application-trigger-recursive-view-returning-current-source-done-gate self-test failed\n");
    exit(1);
}

echo "application-trigger-recursive-view-returning-current-source-done-gate self-test passed\n";
