<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLiteTriggerRecursiveViewDeleteReturningCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewDeleteReturningCurrentSourceNextPlan;

$rows = [
    ['key_name' => 'plugin_root', 'key_value' => 'root', 'load_policy' => 'yes', 'parent_key_name' => null, 'priority' => 0],
    ['key_name' => 'plugin_child_a', 'key_value' => 'child-a', 'load_policy' => 'yes', 'parent_key_name' => 'plugin_root', 'priority' => 10],
    ['key_name' => 'plugin_child_b', 'key_value' => 'child-b', 'load_policy' => 'no', 'parent_key_name' => 'plugin_root', 'priority' => 20],
    ['key_name' => 'plugin_grandchild', 'key_value' => 'grandchild', 'load_policy' => 'no', 'parent_key_name' => 'plugin_child_a', 'priority' => 30],
    ['key_name' => 'siteurl', 'key_value' => 'https://example.test', 'load_policy' => 'yes', 'parent_key_name' => null, 'priority' => 40],
    ['key_name' => 'plugin_next_root', 'key_value' => 'next-root', 'load_policy' => 'yes', 'parent_key_name' => null, 'priority' => 50],
    ['key_name' => 'plugin_next_child', 'key_value' => 'next-child', 'load_policy' => 'no', 'parent_key_name' => 'plugin_next_root', 'priority' => 60],
];

$currentView = [
    'name' => 'app_recursive_setting_delete_view',
    'source' => 'main@view-cookie-168-current',
    'trigger' => 'app_recursive_setting_delete_view_io_delete',
    'trigger_source' => 'main@trigger-cookie-168-current',
    'root_key' => 'root_name',
    'columns' => ['key_name', 'key_value', 'load_policy', 'parent_key_name', 'priority'],
];
$nextView = $currentView;
$nextView['source'] = 'main@view-cookie-168-next';
$nextView['trigger_source'] = 'main@trigger-cookie-168-next';

$returning = [
    'old.key_name',
    ['expr' => 'old.key_value', 'as' => 'value'],
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
        'savepoint' => 'app_recursive_view_delete_168',
        'release_current' => true,
        'admit_next_source' => true,
    ],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'trigger-recursive-view-delete-returning-next-source-admitted-next168');
    assert($plan['current_deleted_keys'] === ['plugin_root', 'plugin_child_a', 'plugin_child_b', 'plugin_grandchild']);
    assert($plan['next_deleted_keys'] === ['plugin_next_root', 'plugin_next_child']);
    assert(array_column($plan['after_savepoint'], 'key_name') === ['siteurl']);
    assert($plan['changes'] === 6);
    echo "application-trigger-recursive-view-delete-returning-current-source-next self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
