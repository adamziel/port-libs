<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$base = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes'],
];
$currentView = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-180-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-180-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    'audit_label' => 'current-recursive-view-trigger-180',
];
$nextView = $currentView;
$nextView['source'] = 'main@view-cookie-180-next';
$nextView['trigger_source'] = 'main@trigger-cookie-180-next';
$nextView['columns'] = ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child', 'import_source'];
$nextView['mapping']['import_source'] = 'source';
$nextView['audit_label'] = 'next-recursive-view-trigger-180';

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeResetGenerationCurrentSourceFence(
    $base,
    [
        ['import_id' => 10, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
        ['import_id' => 11, 'name' => 'current_module', 'value' => 'enabled', 'load_policy_flag' => 'no', 'spawn_child' => true],
    ],
    [
        ['import_id' => 20, 'name' => 'landing_url', 'value' => 'https://next.test', 'load_policy_flag' => 'yes', 'spawn_child' => true, 'import_source' => 'next-cookie'],
        ['import_id' => 21, 'name' => 'next_module', 'value' => 'active', 'load_policy_flag' => 'no', 'spawn_child' => false, 'import_source' => 'next-cookie'],
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
        'savepoint' => 'app_recursive_view_180',
        'cursor_name' => 'app_recursive_view_returning_cursor_180',
        'page_size' => 3,
    ],
);

if (
    $summary['status_next180'] !== 'trigger-recursive-view-returning-current-source-next180-current-source-held'
    || $summary['source_changed_next180'] !== true
    || $summary['source_snapshot_next180']['current_rows_visible'] !== 6
    || $summary['source_snapshot_next180']['held_next_rows'] !== 4
    || array_column($summary['visible_returning_rows_next180'], 'name') !== ['base_url', 'current_module', 'base_url:child', 'current_module:child', 'base_url:child:child', 'current_module:child:child']
    || array_column($summary['held_returning_rows_next180'], 'name') !== ['landing_url', 'next_module', 'landing_url:child', 'landing_url:child:child']
) {
    fwrite(STDERR, "application-trigger-recursive-view-returning-current-source-next180 self-test failed\n");
    exit(1);
}

echo "application-trigger-recursive-view-returning-current-source-next180 self-test passed\n";
