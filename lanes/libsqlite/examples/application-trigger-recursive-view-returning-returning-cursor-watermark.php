<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$baseRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.example', 'autoload' => 'yes', 'source' => 'seed'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.example', 'autoload' => 'yes', 'source' => 'seed'],
];
$currentView = [
    'name' => 'wp_option_import_view',
    'source' => 'main@view-cookie-171-current',
    'trigger' => 'wp_option_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-171-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_retry',
    'audit_label' => 'current-recursive-trigger-cursor',
];
$nextView = [
    'name' => 'wp_option_import_view',
    'source' => 'main@view-cookie-171-next',
    'trigger' => 'wp_option_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-171-next',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'origin'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'origin' => 'source'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_next_retry',
    'audit_label' => 'next-recursive-trigger-cursor',
];
$currentRows = [
    ['import_id' => 10, 'name' => 'plugin_seed', 'value' => 'enabled', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'siteurl', 'value' => 'https://current.example', 'autoload_flag' => 'yes', 'spawn_child' => false],
];
$nextRows = [
    ['import_id' => 20, 'name' => 'rewrite_rules', 'value' => 'cached', 'autoload_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'home', 'value' => 'https://next-home.example', 'autoload_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => false],
];
$returning = [
    'new.option_name',
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'old.option_value', 'as' => 'old_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
];

$plan = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeReturningCursorWatermark(
    $baseRows,
    $currentRows,
    $nextRows,
    $currentView,
    $nextView,
    $returning,
    [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_171',
        'page_size' => 2,
        'max_depth' => 2,
        'admit_next_source' => true,
        'acknowledged_current_pages' => 1,
    ],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status_next171'] === 'trigger-recursive-view-returning-current-source-cursor-open-next171');
    assert($plan['next_source_fenced_by_open_returning_cursor'] === true);
    assert($plan['next_source_visible_after_cursor_close'] === false);
    assert($plan['current_returning_acknowledged_pages'][0]['names'] === ['plugin_seed', 'siteurl']);
    assert($plan['current_returning_pending_pages'][0]['names'] === ['plugin_seed_retry', 'plugin_seed_retry_retry']);
    assert($plan['blocked_next_source_pages_next171'][0]['names'] === ['rewrite_rules', 'home']);
    echo "application-trigger-recursive-view-returning-current-source-next171 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status_next171'],
    'cursor' => $plan['cursor_watermark_next171'],
    'visible_pages' => array_column($plan['visible_returning_pages_next171'], 'names'),
    'pending_current_pages' => array_column($plan['current_returning_pending_pages'], 'names'),
    'blocked_next_pages' => array_column($plan['blocked_next_source_pages_next171'], 'names'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
