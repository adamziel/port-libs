<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$base = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes'],
];
$currentView = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-172-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-172-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    'audit_label' => 'current-recursive-view-trigger',
];
$nextView = $currentView;
$nextView['source'] = 'main@view-cookie-172-next';
$nextView['trigger_source'] = 'main@trigger-cookie-172-next';
$nextView['audit_label'] = 'next-recursive-view-trigger';
$returning = [
    ['expr' => 'new.key_name', 'as' => 'name'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
];

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeRecursiveChildYieldStream(
    $base,
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
    $returning,
    ['savepoint' => 'app_recursive_view_172'],
);

if (
    $summary['status'] !== 'trigger-recursive-view-returning-current-source-next172-current-pinned'
    || array_column($summary['visible_returning_rows'], 'name') !== ['base_url', 'current_module', 'base_url:child', 'current_module:child', 'base_url:child:child', 'current_module:child:child']
    || array_column($summary['suppressed_returning_rows'], 'name') !== ['landing_url', 'next_module', 'landing_url:child', 'landing_url:child:child']
) {
    fwrite(STDERR, "application-trigger-recursive-view-returning-current-source-next172 self-test failed\n");
    exit(1);
}

echo "application-trigger-recursive-view-returning-current-source-next172 self-test passed\n";
