<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows = [
    ['key_name' => 'base_url', 'key_value' => 'https://example.test', 'load_policy' => 'yes', 'parent_name' => null, 'priority' => 0],
    ['key_name' => 'theme_root', 'key_value' => 'theme', 'load_policy' => 'yes', 'parent_name' => 'base_url', 'priority' => 10],
    ['key_name' => 'theme_child', 'key_value' => 'theme-child', 'load_policy' => 'no', 'parent_name' => 'theme_root', 'priority' => 20],
    ['key_name' => 'module_root', 'key_value' => 'module', 'load_policy' => 'yes', 'parent_name' => 'base_url', 'priority' => 15],
    ['key_name' => 'module_child', 'key_value' => 'module-child', 'load_policy' => 'no', 'parent_name' => 'module_root', 'priority' => 30],
    ['key_name' => 'group_root', 'key_value' => 'group', 'load_policy' => 'yes', 'parent_name' => 'module_child', 'priority' => 40],
    ['key_name' => 'group_child', 'key_value' => 'group-child', 'load_policy' => 'no', 'parent_name' => 'group_root', 'priority' => 50],
];

$currentView = [
    'name' => 'app_recursive_load_policy_view',
    'source' => 'main@view-cookie-174-current',
    'trigger' => 'app_recursive_load_policy_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-174-current',
    'root_key' => 'root_name',
    'parent_key' => 'parent_name',
    'columns' => ['key_name', 'key_value', 'load_policy', 'parent_name', 'priority'],
    'where' => static fn (array $row, string $root, int $depth): bool => $depth <= 2 && !str_starts_with((string) $row['key_name'], 'network_'),
    'order_by' => 'priority',
];
$nextView = $currentView;
$nextView['source'] = 'main@view-cookie-174-next';
$nextView['trigger_source'] = 'main@trigger-cookie-174-next';
$nextView['where'] = static fn (array $row, string $root, int $depth): bool => $depth <= 3 && str_contains((string) $row['key_name'], '_');

$plan = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNextViewSourceHoldFence(
    $rows,
    [['root_name' => 'base_url']],
    [['root_name' => 'theme_root']],
    [['root_name' => 'module_root']],
    $currentView,
    $nextView,
    [
        'key_name',
        ['expr' => 'root', 'as' => 'root_name'],
        ['expr' => 'depth', 'as' => 'depth'],
        ['expr' => 'trigger_source', 'as' => 'trigger_cookie'],
    ],
    [
        'release_staged_sources' => 2,
        'savepoint' => 'app_recursive_view_next174',
        'cursor_name' => 'app_recursive_view_returning_cursor_174',
        'current_generation' => 'app-import-current-174',
        'first_next_generation' => 'app-import-next-174-a',
        'second_next_generation' => 'app-import-next-174-b',
        'current_schema_cookie' => 174,
        'next_schema_cookie' => 175,
        'reprepare_token' => 'app.reprepare.174',
        'expected_reprepare_token' => 'app.reprepare.174',
    ],
);

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    assert($plan['status'] === 'trigger-recursive-view-returning-current-source-watermark-held-next174');
    assert($plan['statement_rows'] === 6);
    assert($plan['conflict_keys'] === ['theme_child', 'module_child']);
    assert($plan['current_source_watermark']['current_drained_before_next'] === true);

    echo "application-trigger-recursive-view-returning-current-source-next174 self-test passed\n";
}

return [
    'scenario' => 'application-trigger-recursive-view-returning-current-source-next174',
    'applicationUse' => 'Copied app_settings imports through recursive INSTEAD OF view triggers keep current-source RETURNING rows immutable when a reparsed next-source cursor would yield duplicate key_name rows.',
    'status' => $plan['status'],
    'visibleRows' => $plan['statement_rows'],
    'conflictKeys' => $plan['conflict_keys'],
    'sourceChanged' => $plan['base']['source_changed'],
    'dependencyClosure' => $plan['dependency_closure'],
];
