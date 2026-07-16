<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'source' => 'seed'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes', 'source' => 'seed'],
];
$currentView = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-admission-current',
    'trigger' => 'app_setting_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-admission-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_child',
    'audit_label' => 'current-recursive-trigger-body',
];
$nextView = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-admission-next',
    'trigger' => 'app_setting_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-admission-next',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'origin'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'origin' => 'source'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_next_child',
    'audit_label' => 'next-recursive-trigger-body',
];
$returning = [
    'new.key_name',
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'depth', 'as' => 'depth_value'],
];
$plan = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeRecursiveViewCurrentSourceAdmission(
    $rows,
    [
        ['import_id' => 10, 'name' => 'module_seed', 'value' => 'enabled', 'load_policy_flag' => 'yes'],
        ['import_id' => 11, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
    ],
    [
        ['import_id' => 20, 'name' => 'routing_rules', 'value' => 'cached', 'load_policy_flag' => 'yes', 'origin' => 'next-import'],
        ['import_id' => 21, 'name' => 'landing_url', 'value' => 'https://next-landing_url.test', 'load_policy_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => false],
    ],
    $currentView,
    $nextView,
    $returning,
    ['key' => 'key_name', 'savepoint' => 'app_recursive_view_admission', 'max_depth' => 2],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'trigger-recursive-view-returning-current-source-pinned');
    assert(array_column(array_column($plan['current_returning_rows'], 'returning'), 'key_name') === ['module_seed', 'base_url', 'module_seed_child', 'module_seed_child_child']);
    assert($plan['next_returning_rows'] === []);
    assert(array_column(array_column($plan['attempted_next_returning_rows'], 'returning'), 'key_name') === ['routing_rules', 'landing_url', 'routing_rules_next_child', 'routing_rules_next_child_next_child']);
    assert(array_column($plan['after_savepoint'], 'key_name') === ['base_url', 'landing_url']);
    echo "application-trigger-recursive-view-returning-current-source-admission self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'current_returning' => array_column(array_column($plan['current_returning_rows'], 'returning'), 'key_name'),
    'attempted_next_returning' => array_column(array_column($plan['attempted_next_returning_rows'], 'returning'), 'key_name'),
    'visible_view_source' => $plan['visible_view']['source'],
    'yield_boundary' => $plan['yield_boundary'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
