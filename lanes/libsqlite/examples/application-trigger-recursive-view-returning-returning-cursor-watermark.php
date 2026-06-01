<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$baseRows = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.example', 'load_policy' => 'yes', 'source' => 'seed'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.example', 'load_policy' => 'yes', 'source' => 'seed'],
];
$currentView = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-171-current',
    'trigger' => 'app_setting_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-171-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_retry',
    'audit_label' => 'current-recursive-trigger-cursor',
];
$nextView = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-171-next',
    'trigger' => 'app_setting_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-171-next',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'origin'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'origin' => 'source'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_next_retry',
    'audit_label' => 'next-recursive-trigger-cursor',
];
$currentRows = [
    ['import_id' => 10, 'name' => 'module_seed', 'value' => 'enabled', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'base_url', 'value' => 'https://current.example', 'load_policy_flag' => 'yes', 'spawn_child' => false],
];
$nextRows = [
    ['import_id' => 20, 'name' => 'routing_rules', 'value' => 'cached', 'load_policy_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'landing_url', 'value' => 'https://next-landing_url.example', 'load_policy_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => false],
];
$returning = [
    'new.key_name',
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'old.key_value', 'as' => 'old_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
];

$plan = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeReturningCursorWatermark(
    $baseRows,
    $currentRows,
    $nextRows,
    $currentView,
    $nextView,
    $returning,
    [
        'key' => 'key_name',
        'savepoint' => 'app_recursive_view_171',
        'page_size' => 2,
        'max_depth' => 2,
        'admit_next_source' => true,
        'acknowledged_current_pages' => 1,
    ],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status_next171'] === 'trigger-recursive-view-returning-current-source-cursor-open-next171');
    assert($plan['next_source_fenced_by_open_returning_cursor'] === true);
    assert($plan['next_source_visible_after_cursor_close'] === false);
    assert($plan['current_returning_acknowledged_pages'][0]['names'] === ['module_seed', 'base_url']);
    assert($plan['current_returning_pending_pages'][0]['names'] === ['module_seed_retry', 'module_seed_retry_retry']);
    assert($plan['blocked_next_source_pages_next171'][0]['names'] === ['routing_rules', 'landing_url']);
    echo "application-trigger-recursive-view-returning-current-source-next171 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status_next171'],
    'cursor' => $plan['cursor_watermark_next171'],
    'visible_pages' => array_column($plan['visible_returning_pages_next171'], 'names'),
    'pending_current_pages' => array_column($plan['current_returning_pending_pages'], 'names'),
    'blocked_next_pages' => array_column($plan['blocked_next_source_pages_next171'], 'names'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
