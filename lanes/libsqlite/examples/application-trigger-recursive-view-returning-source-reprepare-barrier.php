<?php

declare(strict_types=1);

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
    'source' => 'main@view-cookie-170-current',
    'trigger' => 'app_recursive_load_policy_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-170-current',
    'root_key' => 'root_name',
    'parent_key' => 'parent_name',
    'columns' => ['key_name', 'key_value', 'load_policy', 'parent_name', 'priority'],
    'where' => static fn (array $row, string $root, int $depth): bool => $depth <= 2 && !str_starts_with((string) $row['key_name'], 'network_'),
    'order_by' => 'priority',
];
$nextView = $currentView;
$nextView['source'] = 'main@view-cookie-170-next';
$nextView['trigger_source'] = 'main@trigger-cookie-170-next';
$nextView['where'] = static fn (array $row, string $root, int $depth): bool => $depth <= 3 && str_contains((string) $row['key_name'], '_');

$returning = [
    'key_name',
    ['expr' => 'root', 'as' => 'root_name'],
    ['expr' => 'depth', 'as' => 'depth'],
    ['expr' => 'trigger_source', 'as' => 'trigger_cookie'],
];

$plan = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeSourceReprepareBarrier(
    $rows,
    [['root_name' => 'base_url']],
    [['root_name' => 'theme_root']],
    [['root_name' => 'module_root']],
    $currentView,
    $nextView,
    $returning,
    [
        'release_staged_sources' => 2,
        'savepoint' => 'app_recursive_view_next170',
        'cursor_name' => 'app_recursive_view_returning_cursor_170',
        'current_generation' => 'app-import-current-170',
        'first_next_generation' => 'app-import-next-170-a',
        'second_next_generation' => 'app-import-next-170-b',
        'current_schema_cookie' => 17,
        'next_schema_cookie' => 18,
        'reprepare_token' => 'app.reprepare.170',
        'expected_reprepare_token' => 'app.reprepare.170.expected',
    ],
);

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    assert($plan['status'] === 'trigger-recursive-view-returning-current-source-reprepare-held-next170');
    assert($plan['statement_rows'] === 4);
    assert($plan['returning_barrier']['staged_source_held'] === 4);
    assert($plan['current_drained_before_next'] === true);

    echo "application-trigger-recursive-view-returning-current-source-next170 self-test passed\n";
}

return [
    'scenario' => 'application-trigger-recursive-view-returning-current-source-next170',
    'applicationUse' => 'Copied app_settings imports through recursive INSTEAD OF view triggers keep RETURNING rows pinned to the current view/trigger source until a matching reprepare token admits the next source.',
    'status' => $plan['status'],
    'visibleRows' => $plan['statement_rows'],
    'heldRows' => $plan['returning_barrier']['staged_source_held'],
    'sourceChanged' => $plan['source_changed'],
    'dependencyClosure' => $plan['dependency_closure'],
];
