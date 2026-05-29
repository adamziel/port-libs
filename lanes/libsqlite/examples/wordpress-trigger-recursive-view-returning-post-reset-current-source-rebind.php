<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$currentView = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-186-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-186-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload'],
    'audit_label' => 'current-recursive-view-trigger-186',
];
$nextView = $currentView;
$nextView['source'] = 'main@view-cookie-186-next';
$nextView['trigger_source'] = 'main@trigger-cookie-186-next';
$nextView['audit_label'] = 'next-recursive-view-trigger-186';
$postResetView = $currentView;
$postResetView['source'] = 'main@view-cookie-186-post-reset';
$postResetView['trigger_source'] = 'main@trigger-cookie-186-post-reset';
$postResetView['audit_label'] = 'post-reset-recursive-view-trigger-186';

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executePostResetCurrentSourceRebind(
    [
        ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
        ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
    ],
    [
        ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
    ],
    [
        ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
    ],
    $currentView,
    $nextView,
    [
        ['expr' => 'new.option_name', 'as' => 'name'],
        ['expr' => 'new.option_value', 'as' => 'value'],
        ['expr' => 'event', 'as' => 'event_name'],
        ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ],
    [
        'savepoint' => 'wp_recursive_view_186',
        'cursor_name' => 'wp_recursive_view_returning_cursor_186',
        'admit_next_source' => true,
        'reset_generation' => 'wp-current-reset-186',
        'post_reset_current_source_token' => 'wp.current.source.postreset.186',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.186',
        'post_reset_view' => $postResetView,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes'],
            ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no'],
        ],
    ],
);

if (
    $summary['status_next186'] !== 'trigger-recursive-view-returning-current-source-next186-post-reset-rebound'
    || $summary['stale_returning_row_count_next186'] !== 6
    || $summary['fresh_returning_row_count_next186'] !== 2
    || array_column($summary['fresh_returning_payloads_next186'], 'name') !== ['siteurl', 'rewrite_rules']
) {
    fwrite(STDERR, "wordpress-trigger-recursive-view-returning-current-source-next186 self-test failed\n");
    exit(1);
}

echo "wordpress-trigger-recursive-view-returning-current-source-next186 self-test passed\n";
