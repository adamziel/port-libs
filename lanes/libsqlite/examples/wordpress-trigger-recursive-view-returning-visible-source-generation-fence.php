<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'source' => 'seed'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'source' => 'seed'],
];
$currentView = [
    'name' => 'wp_option_import_view',
    'source' => 'main@view-cookie-173-current',
    'trigger' => 'wp_option_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-173-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_retry',
    'audit_label' => 'current-recursive-trigger-drain-173',
];
$nextView = [
    'name' => 'wp_option_import_view',
    'source' => 'main@view-cookie-173-next',
    'trigger' => 'wp_option_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-173-next',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'origin'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'origin' => 'source'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_next_retry',
    'audit_label' => 'next-recursive-trigger-drain-173',
];
$returning = [
    'new.option_name',
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'old.option_value', 'as' => 'old_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
];

$plan = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeVisibleSourceGenerationFence(
    $rows,
    [
        ['import_id' => 10, 'name' => 'plugin_seed', 'value' => 'enabled', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ['import_id' => 11, 'name' => 'skip_me', 'value' => 'disabled', 'autoload_flag' => 'skip', 'spawn_child' => true],
        ['import_id' => 12, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
    ],
    [
        ['import_id' => 20, 'name' => 'rewrite_rules', 'value' => 'cached', 'autoload_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => true],
        ['import_id' => 21, 'name' => 'home', 'value' => 'https://next-home.test', 'autoload_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => false],
        ['import_id' => 22, 'name' => 'next_skip', 'value' => 'ignored', 'autoload_flag' => 'skip', 'origin' => 'next-import', 'spawn_child' => true],
    ],
    $currentView,
    $nextView,
    $returning,
    [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_173',
        'max_depth' => 2,
        'page_size' => 2,
        'admit_next_source' => true,
        'drained_current_pages' => 1,
    ],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status_next173'] === 'trigger-recursive-view-returning-current-source-cursor-fences-next-source-next173');
    assert($plan['current_cursor_exhausted'] === false);
    assert($plan['drained_current_pages'][0]['names'] === ['plugin_seed', 'siteurl']);
    assert($plan['pending_current_pages'][0]['names'] === ['plugin_seed_retry', 'plugin_seed_retry_retry']);
    assert($plan['blocked_next_source_pages_next173'][0]['names'] === ['rewrite_rules', 'home']);
    assert($plan['next_source_block_reasons_next173'] === ['current-returning-cursor-not-exhausted']);
    echo "wordpress-trigger-recursive-view-returning-current-source-next173 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status_next173'],
    'drained_current' => array_column($plan['drained_current_pages'], 'names'),
    'pending_current' => array_column($plan['pending_current_pages'], 'names'),
    'blocked_next' => array_column($plan['blocked_next_source_pages_next173'], 'names'),
    'reasons' => $plan['next_source_block_reasons_next173'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
