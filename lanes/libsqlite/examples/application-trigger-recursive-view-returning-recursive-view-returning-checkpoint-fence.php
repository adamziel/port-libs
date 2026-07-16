<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'source' => 'seed'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes', 'source' => 'seed'],
];
$currentView = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-176-current',
    'trigger' => 'app_setting_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-176-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_retry',
    'audit_label' => 'current-recursive-trigger-page-acks-176',
];
$nextView = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-176-next',
    'trigger' => 'app_setting_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-176-next',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'origin'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'origin' => 'source'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_next_retry',
    'audit_label' => 'next-recursive-trigger-page-acks-176',
];
$currentInput = [
    ['import_id' => 10, 'name' => 'module_seed', 'value' => 'enabled', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 12, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
];
$nextInput = [
    ['import_id' => 20, 'name' => 'routing_rules', 'value' => 'cached', 'load_policy_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'landing_url', 'value' => 'https://next-landing_url.test', 'load_policy_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => false],
];
$returning = [
    'new.key_name',
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'old.key_value', 'as' => 'old_value'],
    ['expr' => 'view.name', 'as' => 'view_name'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
];

$plan = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeRecursiveViewReturningCheckpointFence(
    $rows,
    $currentInput,
    $nextInput,
    $currentView,
    $nextView,
    $returning,
    [
        'key' => 'key_name',
        'savepoint' => 'app_recursive_view_176',
        'max_depth' => 2,
        'page_size' => 2,
        'admit_next_source' => true,
        'acknowledged_current_page_indexes' => [0, 1],
    ],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['next_source_admitted_next176'] === true);
    assert($plan['current_page_acknowledgements_valid_next176'] === true);
    assert(array_column($plan['visible_returning_pages_next173'], 'phase') === ['current', 'current', 'next', 'next']);
    echo "application trigger recursive view returning current source next176 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status_next176'],
    'admitted' => $plan['next_source_admitted_next176'],
    'acknowledged_current_page_indexes' => $plan['acknowledged_current_page_indexes_next176'],
    'visible_page_phases' => array_column($plan['visible_returning_pages_next173'], 'phase'),
    'dependency_closure' => 'no-new-support-component',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
