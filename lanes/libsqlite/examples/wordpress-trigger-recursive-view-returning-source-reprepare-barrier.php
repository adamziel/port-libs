<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows = [
    ['option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'parent_name' => null, 'priority' => 0],
    ['option_name' => 'theme_root', 'option_value' => 'theme', 'autoload' => 'yes', 'parent_name' => 'siteurl', 'priority' => 10],
    ['option_name' => 'theme_child', 'option_value' => 'theme-child', 'autoload' => 'no', 'parent_name' => 'theme_root', 'priority' => 20],
    ['option_name' => 'plugin_root', 'option_value' => 'plugin', 'autoload' => 'yes', 'parent_name' => 'siteurl', 'priority' => 15],
    ['option_name' => 'plugin_child', 'option_value' => 'plugin-child', 'autoload' => 'no', 'parent_name' => 'plugin_root', 'priority' => 30],
    ['option_name' => 'network_root', 'option_value' => 'network', 'autoload' => 'yes', 'parent_name' => 'plugin_child', 'priority' => 40],
    ['option_name' => 'network_child', 'option_value' => 'network-child', 'autoload' => 'no', 'parent_name' => 'network_root', 'priority' => 50],
];

$currentView = [
    'name' => 'wp_recursive_autoload_view',
    'source' => 'main@view-cookie-170-current',
    'trigger' => 'wp_recursive_autoload_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-170-current',
    'root_key' => 'root_name',
    'parent_key' => 'parent_name',
    'columns' => ['option_name', 'option_value', 'autoload', 'parent_name', 'priority'],
    'where' => static fn (array $row, string $root, int $depth): bool => $depth <= 2 && !str_starts_with((string) $row['option_name'], 'network_'),
    'order_by' => 'priority',
];
$nextView = $currentView;
$nextView['source'] = 'main@view-cookie-170-next';
$nextView['trigger_source'] = 'main@trigger-cookie-170-next';
$nextView['where'] = static fn (array $row, string $root, int $depth): bool => $depth <= 3 && str_contains((string) $row['option_name'], '_');

$returning = [
    'option_name',
    ['expr' => 'root', 'as' => 'root_name'],
    ['expr' => 'depth', 'as' => 'depth'],
    ['expr' => 'trigger_source', 'as' => 'trigger_cookie'],
];

$plan = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeSourceReprepareBarrier(
    $rows,
    [['root_name' => 'siteurl']],
    [['root_name' => 'theme_root']],
    [['root_name' => 'plugin_root']],
    $currentView,
    $nextView,
    $returning,
    [
        'release_staged_sources' => 2,
        'savepoint' => 'wp_recursive_view_next170',
        'cursor_name' => 'wp_recursive_view_returning_cursor_170',
        'current_generation' => 'wp-import-current-170',
        'first_next_generation' => 'wp-import-next-170-a',
        'second_next_generation' => 'wp-import-next-170-b',
        'current_schema_cookie' => 17,
        'next_schema_cookie' => 18,
        'reprepare_token' => 'wp.reprepare.170',
        'expected_reprepare_token' => 'wp.reprepare.170.expected',
    ],
);

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    assert($plan['status'] === 'trigger-recursive-view-returning-current-source-reprepare-held-next170');
    assert($plan['statement_rows'] === 4);
    assert($plan['returning_barrier']['staged_source_held'] === 4);
    assert($plan['current_drained_before_next'] === true);

    echo "wordpress-trigger-recursive-view-returning-current-source-next170 self-test passed\n";
}

return [
    'scenario' => 'wordpress-trigger-recursive-view-returning-current-source-next170',
    'wordpressUse' => 'Copied wp_options imports through recursive INSTEAD OF view triggers keep RETURNING rows pinned to the current view/trigger source until a matching reprepare token admits the next source.',
    'status' => $plan['status'],
    'visibleRows' => $plan['statement_rows'],
    'heldRows' => $plan['returning_barrier']['staged_source_held'],
    'sourceChanged' => $plan['source_changed'],
    'dependencyClosure' => $plan['dependency_closure'],
];
