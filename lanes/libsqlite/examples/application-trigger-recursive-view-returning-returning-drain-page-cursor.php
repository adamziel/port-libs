<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'source' => 'seed'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'source' => 'seed'],
];
$currentView = [
    'name' => 'wp_option_import_view',
    'source' => 'main@view-cookie-167-current',
    'trigger' => 'wp_option_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-167-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_retry',
    'audit_label' => 'current-recursive-trigger-drain',
];
$nextView = [
    'name' => 'wp_option_import_view',
    'source' => 'main@view-cookie-167-next',
    'trigger' => 'wp_option_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-167-next',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'origin'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'origin' => 'source'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_next_retry',
    'audit_label' => 'next-recursive-trigger-drain',
];
$returning = [
    'new.option_name',
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'old.option_value', 'as' => 'old_value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
];

$plan = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeReturningDrainPageCursor(
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
    ['key' => 'option_name', 'savepoint' => 'wp_recursive_view_167', 'max_depth' => 2, 'page_size' => 2],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status_next167'] === 'trigger-recursive-view-returning-current-source-drain-fenced-next167');
    assert(array_column($plan['current_returning_pages'][0]['rows'], 'trigger_source') === ['main@trigger-cookie-167-current', 'main@trigger-cookie-167-current']);
    assert($plan['current_returning_pages'][0]['names'] === ['plugin_seed', 'siteurl']);
    assert($plan['current_returning_pages'][1]['names'] === ['plugin_seed_retry', 'plugin_seed_retry_retry']);
    assert($plan['blocked_next_source_pages'][0]['names'] === ['rewrite_rules', 'home']);
    assert($plan['next_returning_rows'] === []);
    assert($plan['attempted_next_source_blocked_by_current_drain'] === true);
    echo "application-trigger-recursive-view-returning-current-source-next167 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status_next167'],
    'current_pages' => array_map(static fn (array $page): array => [
        'cursor' => $page['cursor'],
        'names' => $page['names'],
    ], $plan['current_returning_pages']),
    'blocked_next_pages' => array_map(static fn (array $page): array => [
        'cursor' => $page['cursor'],
        'names' => $page['names'],
    ], $plan['blocked_next_source_pages']),
    'yield_boundary' => $plan['yield_boundary_next167'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
