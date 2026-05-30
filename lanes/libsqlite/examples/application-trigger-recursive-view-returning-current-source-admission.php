<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'source' => 'seed'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'source' => 'seed'],
];
$currentView = [
    'name' => 'wp_option_import_view',
    'source' => 'main@view-cookie-admission-current',
    'trigger' => 'wp_option_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-admission-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_child',
    'audit_label' => 'current-recursive-trigger-body',
];
$nextView = [
    'name' => 'wp_option_import_view',
    'source' => 'main@view-cookie-admission-next',
    'trigger' => 'wp_option_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-admission-next',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'origin'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'origin' => 'source'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_next_child',
    'audit_label' => 'next-recursive-trigger-body',
];
$returning = [
    'new.option_name',
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'depth', 'as' => 'depth_value'],
];
$plan = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeRecursiveViewCurrentSourceAdmission(
    $rows,
    [
        ['import_id' => 10, 'name' => 'plugin_seed', 'value' => 'enabled', 'autoload_flag' => 'yes'],
        ['import_id' => 11, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
    ],
    [
        ['import_id' => 20, 'name' => 'rewrite_rules', 'value' => 'cached', 'autoload_flag' => 'yes', 'origin' => 'next-import'],
        ['import_id' => 21, 'name' => 'home', 'value' => 'https://next-home.test', 'autoload_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => false],
    ],
    $currentView,
    $nextView,
    $returning,
    ['key' => 'option_name', 'savepoint' => 'wp_recursive_view_admission', 'max_depth' => 2],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'trigger-recursive-view-returning-current-source-pinned');
    assert(array_column(array_column($plan['current_returning_rows'], 'returning'), 'option_name') === ['plugin_seed', 'siteurl', 'plugin_seed_child', 'plugin_seed_child_child']);
    assert($plan['next_returning_rows'] === []);
    assert(array_column(array_column($plan['attempted_next_returning_rows'], 'returning'), 'option_name') === ['rewrite_rules', 'home', 'rewrite_rules_next_child', 'rewrite_rules_next_child_next_child']);
    assert(array_column($plan['after_savepoint'], 'option_name') === ['siteurl', 'home']);
    echo "application-trigger-recursive-view-returning-current-source-admission self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'current_returning' => array_column(array_column($plan['current_returning_rows'], 'returning'), 'option_name'),
    'attempted_next_returning' => array_column(array_column($plan['attempted_next_returning_rows'], 'returning'), 'option_name'),
    'visible_view_source' => $plan['visible_view']['source'],
    'yield_boundary' => $plan['yield_boundary'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
