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
    'source' => 'main@view-cookie-164-current',
    'trigger' => 'wp_option_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-164-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_retry',
    'audit_label' => 'current-recursive-trigger-body-retry',
];
$nextView = [
    'name' => 'wp_option_import_view',
    'source' => 'main@view-cookie-164-next',
    'trigger' => 'wp_option_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-164-next',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'origin'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'origin' => 'source'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_next_retry',
    'audit_label' => 'next-recursive-trigger-body-retry',
];
$returning = [
    'new.option_name',
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'old.option_value', 'as' => 'old_value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
];
$plan = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeRecursiveViewUpsertAdmission(
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
    ['key' => 'option_name', 'savepoint' => 'wp_recursive_view_164', 'max_depth' => 2],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'trigger-recursive-view-returning-current-source-pinned-next164');
    assert(array_column(array_column($plan['current_returning_rows'], 'returning'), 'option_name') === ['plugin_seed', 'siteurl', 'plugin_seed_retry', 'plugin_seed_retry_retry']);
    assert(array_column(array_column($plan['current_skipped_rows'], 'returning'), 'option_name') === ['skip_me']);
    assert($plan['current_replaced_keys'] === ['siteurl']);
    assert($plan['next_returning_rows'] === []);
    assert(array_column(array_column($plan['attempted_next_returning_rows'], 'returning'), 'option_name') === ['rewrite_rules', 'home', 'rewrite_rules_next_retry', 'rewrite_rules_next_retry_next_retry']);
    assert(array_column(array_column($plan['attempted_next_skipped_rows'], 'returning'), 'option_name') === ['next_skip']);
    echo "wordpress-trigger-recursive-view-returning-current-source-next164 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'current_returning' => array_column(array_column($plan['current_returning_rows'], 'returning'), 'option_name'),
    'current_skipped' => array_column(array_column($plan['current_skipped_rows'], 'returning'), 'option_name'),
    'current_replaced' => $plan['current_replaced_keys'],
    'attempted_next_returning' => array_column(array_column($plan['attempted_next_returning_rows'], 'returning'), 'option_name'),
    'attempted_next_skipped' => array_column(array_column($plan['attempted_next_skipped_rows'], 'returning'), 'option_name'),
    'yield_boundary' => $plan['yield_boundary'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
