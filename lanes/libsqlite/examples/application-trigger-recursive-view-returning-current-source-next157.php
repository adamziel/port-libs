<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows = [
    ['key_name' => 'base_url', 'key_value' => 'https://example.test', 'load_policy' => 'yes', 'parent_name' => null],
    ['key_name' => 'module_alpha', 'key_value' => 'enabled', 'load_policy' => 'yes', 'parent_name' => 'base_url'],
    ['key_name' => 'module_alpha_child', 'key_value' => 'child-on', 'load_policy' => 'no', 'parent_name' => 'module_alpha'],
    ['key_name' => 'module_beta', 'key_value' => 'disabled', 'load_policy' => 'yes', 'parent_name' => 'base_url'],
    ['key_name' => 'module_next', 'key_value' => 'next-on', 'load_policy' => 'yes', 'parent_name' => 'module_beta'],
];

$view = [
    'name' => 'app_recursive_setting_view',
    'source' => 'main@view-cookie-157-current',
    'trigger' => 'app_recursive_setting_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-157-current',
    'root_key' => 'root_name',
    'parent_key' => 'parent_name',
    'columns' => ['key_name', 'key_value', 'load_policy', 'parent_name'],
    'where' => static fn (array $row, string $root, int $depth): bool => $depth <= 2 && str_starts_with((string) $row['key_name'], 'module_'),
    'order_by' => 'key_name',
];
$nextView = array_replace($view, [
    'source' => 'main@view-cookie-157-next',
    'trigger_source' => 'main@trigger-cookie-157-next',
]);

$plan = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeRecursiveViewSourceHandoff(
    $rows,
    [['root_name' => 'base_url']],
    [['root_name' => 'module_beta']],
    $view,
    $nextView,
    [
        'key_name',
        ['expr' => 'root', 'as' => 'root_name'],
        ['expr' => 'depth', 'as' => 'recursive_depth'],
        ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'trigger-recursive-view-returning-current-source-pinned-next157');
    assert(count($plan['current_returning_rows']) === 4);
    assert($plan['next_returning_rows'] === []);
    assert($plan['attempted_next_returning_rows'][0]['returning']['key_name'] === 'audit:next:module_beta:module_next');
    echo "application-trigger-recursive-view-returning-current-source-next157 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-trigger-recursive-view-returning-current-source-next157',
    'status' => $plan['status'],
    'currentReturning' => array_column(array_column($plan['current_returning_rows'], 'returning'), 'key_name'),
    'attemptedNextReturning' => array_column(array_column($plan['attempted_next_returning_rows'], 'returning'), 'key_name'),
    'yieldBoundary' => $plan['yield_boundary'],
], JSON_PRETTY_PRINT) . "\n";
