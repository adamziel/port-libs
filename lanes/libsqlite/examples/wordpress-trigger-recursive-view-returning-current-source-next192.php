<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$currentView = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-192-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-192-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload'],
    'audit_label' => 'current-recursive-view-trigger-192',
];
$nextView = $currentView;
$nextView['source'] = 'main@view-cookie-192-next';
$nextView['trigger_source'] = 'main@trigger-cookie-192-next';
$nextView['audit_label'] = 'next-recursive-view-trigger-192';
$postResetView = $currentView;
$postResetView['source'] = 'main@view-cookie-192-post-reset';
$postResetView['trigger_source'] = 'main@trigger-cookie-192-post-reset';
$followingView = $currentView;
$followingView['source'] = 'main@view-cookie-192-following';
$followingView['trigger_source'] = 'main@trigger-cookie-192-following';

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext192(
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
        ['expr' => 'ordinal', 'as' => 'ordinal_value'],
        ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ],
    [
        'savepoint' => 'wp_recursive_view_192',
        'cursor_name' => 'wp_recursive_view_returning_cursor_192',
        'admit_next_source' => true,
        'rollback_token' => 'wp.rollback.current.192',
        'reset_generation' => 'wp-current-reset-192',
        'post_reset_current_source_token' => 'wp.current.source.postreset.192',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.192',
        'post_reset_view' => $postResetView,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.192',
        'next_cursor' => 'wp.returning.next.cursor.192',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.192',
        'following_current_source_token' => 'wp.current.source.following.192',
        'following_cursor' => 'wp.returning.following.cursor.192',
        'following_current_view' => $followingView,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
        ],
    ],
);

if (
    $summary['status_next192'] !== 'trigger-recursive-view-returning-current-source-next192-following-current-visible'
    || $summary['cursor_close_plan_next192']['decision'] !== 'admit-following-current-after-next-cursor-close'
    || array_column($summary['following_current_payloads_next192'], 'name') !== ['blogdescription', 'stylesheet']
) {
    fwrite(STDERR, "wordpress-trigger-recursive-view-returning-current-source-next192 self-test failed\n");
    exit(1);
}

echo "wordpress-trigger-recursive-view-returning-current-source-next192 self-test passed\n";
