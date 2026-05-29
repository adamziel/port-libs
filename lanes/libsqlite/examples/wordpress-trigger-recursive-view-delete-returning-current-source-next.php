<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLiteTriggerRecursiveViewDeleteReturningCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewDeleteReturningCurrentSourceNextPlan;

$rows = [
    ['option_name' => 'plugin_root', 'option_value' => 'root', 'autoload' => 'yes', 'parent_name' => null, 'priority' => 0],
    ['option_name' => 'plugin_child_a', 'option_value' => 'child-a', 'autoload' => 'yes', 'parent_name' => 'plugin_root', 'priority' => 10],
    ['option_name' => 'plugin_child_b', 'option_value' => 'child-b', 'autoload' => 'no', 'parent_name' => 'plugin_root', 'priority' => 20],
    ['option_name' => 'plugin_grandchild', 'option_value' => 'grandchild', 'autoload' => 'no', 'parent_name' => 'plugin_child_a', 'priority' => 30],
    ['option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'parent_name' => null, 'priority' => 40],
    ['option_name' => 'plugin_next_root', 'option_value' => 'next-root', 'autoload' => 'yes', 'parent_name' => null, 'priority' => 50],
    ['option_name' => 'plugin_next_child', 'option_value' => 'next-child', 'autoload' => 'no', 'parent_name' => 'plugin_next_root', 'priority' => 60],
];

$currentView = [
    'name' => 'wp_recursive_option_delete_view',
    'source' => 'main@view-cookie-168-current',
    'trigger' => 'wp_recursive_option_delete_view_io_delete',
    'trigger_source' => 'main@trigger-cookie-168-current',
    'root_key' => 'root_name',
    'columns' => ['option_name', 'option_value', 'autoload', 'parent_name', 'priority'],
];
$nextView = $currentView;
$nextView['source'] = 'main@view-cookie-168-next';
$nextView['trigger_source'] = 'main@trigger-cookie-168-next';

$returning = [
    'old.option_name',
    ['expr' => 'old.option_value', 'as' => 'value'],
    ['expr' => 'depth', 'as' => 'delete_depth'],
    ['expr' => 'trigger_source', 'as' => 'trigger_cookie'],
];

$plan = SQLiteTriggerRecursiveViewDeleteReturningCurrentSourceNextPlan::execute(
    $rows,
    [['root_name' => 'plugin_root']],
    [['root_name' => 'plugin_next_root']],
    $currentView,
    $nextView,
    $returning,
    [
        'savepoint' => 'wp_recursive_view_delete_168',
        'release_current' => true,
        'admit_next_source' => true,
    ],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'trigger-recursive-view-delete-returning-next-source-admitted-next168');
    assert($plan['current_deleted_keys'] === ['plugin_root', 'plugin_child_a', 'plugin_child_b', 'plugin_grandchild']);
    assert($plan['next_deleted_keys'] === ['plugin_next_root', 'plugin_next_child']);
    assert(array_column($plan['after_savepoint'], 'option_name') === ['siteurl']);
    assert($plan['changes'] === 6);
    echo "wordpress-trigger-recursive-view-delete-returning-current-source-next self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
