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
    'source' => 'main@view-cookie-174-current',
    'trigger' => 'wp_recursive_autoload_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-174-current',
    'root_key' => 'root_name',
    'parent_key' => 'parent_name',
    'columns' => ['option_name', 'option_value', 'autoload', 'parent_name', 'priority'],
    'where' => static fn (array $row, string $root, int $depth): bool => $depth <= 2 && !str_starts_with((string) $row['option_name'], 'network_'),
    'order_by' => 'priority',
];
$nextView = $currentView;
$nextView['source'] = 'main@view-cookie-174-next';
$nextView['trigger_source'] = 'main@trigger-cookie-174-next';
$nextView['where'] = static fn (array $row, string $root, int $depth): bool => $depth <= 3 && str_contains((string) $row['option_name'], '_');

$plan = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNextViewSourceHoldFence(
    $rows,
    [['root_name' => 'siteurl']],
    [['root_name' => 'theme_root']],
    [['root_name' => 'plugin_root']],
    $currentView,
    $nextView,
    [
        'option_name',
        ['expr' => 'root', 'as' => 'root_name'],
        ['expr' => 'depth', 'as' => 'depth'],
        ['expr' => 'trigger_source', 'as' => 'trigger_cookie'],
    ],
    [
        'release_staged_sources' => 2,
        'savepoint' => 'wp_recursive_view_next174',
        'cursor_name' => 'wp_recursive_view_returning_cursor_174',
        'current_generation' => 'wp-import-current-174',
        'first_next_generation' => 'wp-import-next-174-a',
        'second_next_generation' => 'wp-import-next-174-b',
        'current_schema_cookie' => 174,
        'next_schema_cookie' => 175,
        'reprepare_token' => 'wp.reprepare.174',
        'expected_reprepare_token' => 'wp.reprepare.174',
    ],
);

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    assert($plan['status'] === 'trigger-recursive-view-returning-current-source-watermark-held-next174');
    assert($plan['statement_rows'] === 6);
    assert($plan['conflict_keys'] === ['theme_child', 'plugin_child']);
    assert($plan['current_source_watermark']['current_drained_before_next'] === true);

    echo "wordpress-trigger-recursive-view-returning-current-source-next174 self-test passed\n";
}

return [
    'scenario' => 'wordpress-trigger-recursive-view-returning-current-source-next174',
    'wordpressUse' => 'Copied wp_options imports through recursive INSTEAD OF view triggers keep current-source RETURNING rows immutable when a reparsed next-source cursor would yield duplicate option_name rows.',
    'status' => $plan['status'],
    'visibleRows' => $plan['statement_rows'],
    'conflictKeys' => $plan['conflict_keys'],
    'sourceChanged' => $plan['base']['source_changed'],
    'dependencyClosure' => $plan['dependency_closure'],
];
