<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'source' => 'seed'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes', 'source' => 'seed'],
];
$currentView = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-173-current',
    'trigger' => 'app_setting_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-173-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_retry',
    'audit_label' => 'current-recursive-trigger-drain-173',
];
$nextView = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-173-next',
    'trigger' => 'app_setting_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-173-next',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'origin'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'origin' => 'source'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_next_retry',
    'audit_label' => 'next-recursive-trigger-drain-173',
];
$returning = [
    'new.key_name',
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'old.key_value', 'as' => 'old_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
];

$plan = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeVisibleSourceGenerationFence(
    $rows,
    [
        ['import_id' => 10, 'name' => 'module_seed', 'value' => 'enabled', 'load_policy_flag' => 'yes', 'spawn_child' => true],
        ['import_id' => 11, 'name' => 'skip_me', 'value' => 'disabled', 'load_policy_flag' => 'skip', 'spawn_child' => true],
        ['import_id' => 12, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
    ],
    [
        ['import_id' => 20, 'name' => 'routing_rules', 'value' => 'cached', 'load_policy_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => true],
        ['import_id' => 21, 'name' => 'landing_url', 'value' => 'https://next-landing_url.test', 'load_policy_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => false],
        ['import_id' => 22, 'name' => 'next_skip', 'value' => 'ignored', 'load_policy_flag' => 'skip', 'origin' => 'next-import', 'spawn_child' => true],
    ],
    $currentView,
    $nextView,
    $returning,
    [
        'key' => 'key_name',
        'savepoint' => 'app_recursive_view_173',
        'max_depth' => 2,
        'page_size' => 2,
        'admit_next_source' => true,
        'drained_current_pages' => 1,
    ],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status_next173'] === 'trigger-recursive-view-returning-current-source-cursor-fences-next-source-next173');
    assert($plan['current_cursor_exhausted'] === false);
    assert($plan['drained_current_pages'][0]['names'] === ['module_seed', 'base_url']);
    assert($plan['pending_current_pages'][0]['names'] === ['module_seed_retry', 'module_seed_retry_retry']);
    assert($plan['blocked_next_source_pages_next173'][0]['names'] === ['routing_rules', 'landing_url']);
    assert($plan['next_source_block_reasons_next173'] === ['current-returning-cursor-not-exhausted']);
    echo "application-trigger-recursive-view-returning-current-source-next173 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status_next173'],
    'drained_current' => array_column($plan['drained_current_pages'], 'names'),
    'pending_current' => array_column($plan['pending_current_pages'], 'names'),
    'blocked_next' => array_column($plan['blocked_next_source_pages_next173'], 'names'),
    'reasons' => $plan['next_source_block_reasons_next173'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
