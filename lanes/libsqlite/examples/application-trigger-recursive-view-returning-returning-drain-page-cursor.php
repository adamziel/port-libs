<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'source' => 'seed'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes', 'source' => 'seed'],
];
$currentView = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-167-current',
    'trigger' => 'app_setting_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-167-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_retry',
    'audit_label' => 'current-recursive-trigger-drain',
];
$nextView = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-167-next',
    'trigger' => 'app_setting_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-167-next',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'origin'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'origin' => 'source'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_next_retry',
    'audit_label' => 'next-recursive-trigger-drain',
];
$returning = [
    'new.key_name',
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'old.key_value', 'as' => 'old_value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
];

$plan = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeReturningDrainPageCursor(
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
    ['key' => 'key_name', 'savepoint' => 'app_recursive_view_167', 'max_depth' => 2, 'page_size' => 2],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status_next167'] === 'trigger-recursive-view-returning-current-source-drain-fenced-next167');
    assert(array_column($plan['current_returning_pages'][0]['rows'], 'trigger_source') === ['main@trigger-cookie-167-current', 'main@trigger-cookie-167-current']);
    assert($plan['current_returning_pages'][0]['names'] === ['module_seed', 'base_url']);
    assert($plan['current_returning_pages'][1]['names'] === ['module_seed_retry', 'module_seed_retry_retry']);
    assert($plan['blocked_next_source_pages'][0]['names'] === ['routing_rules', 'landing_url']);
    assert($plan['next_returning_rows'] === []);
    assert($plan['attempted_next_source_blocked_by_current_drain'] === true);
    echo "application-trigger-recursive-view-returning-current-source-next167 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status_next167'],
    'current_pages' => array_map(static fn (array $page): array => [
        'cursor' => $page['cursor'],
        'names' => $page['names'],
    ], $plan['current_returning_pages']),
    'blocked_next_pages' => array_map(static fn (array $page): array => [
        'cursor' => $page['cursor'],
        'names' => $page['names'],
    ], $plan['blocked_next_source_pages']),
    'yield_boundary' => $plan['yield_boundary_next167'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
