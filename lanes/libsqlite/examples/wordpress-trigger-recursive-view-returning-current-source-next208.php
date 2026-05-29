<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
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
    'source' => 'main@view-cookie-208-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-208-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-208',
];
$nextView = $currentView;
$nextView['source'] = 'main@view-cookie-208-next';
$nextView['trigger_source'] = 'main@trigger-cookie-208-next';
$postResetView = $currentView;
$postResetView['source'] = 'main@view-cookie-208-post-reset';
$postResetView['trigger_source'] = 'main@trigger-cookie-208-post-reset';
$followingView = $currentView;
$followingView['source'] = 'main@view-cookie-208-following';
$followingView['trigger_source'] = 'main@trigger-cookie-208-following';

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentCursorCloseFence(
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
        ['expr' => 'depth', 'as' => 'depth_value'],
        ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
        ['expr' => 'spawn_child', 'as' => 'spawn_child'],
    ],
    [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_208',
        'cursor_name' => 'wp_recursive_view_returning_cursor_208',
        'current_generation' => 'wp-current-returning-208',
        'next_generation' => 'wp-next-returning-208',
        'page_size' => 3,
        'admit_next_source' => true,
        'current_source_token' => 'wp.current.source.208',
        'drain_ack_token' => 'wp.returning.drain.208',
        'rollback_token' => 'wp.rollback.current.208',
        'reset_generation' => 'wp-current-reset-208',
        'post_reset_current_source_token' => 'wp.current.source.postreset.208',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.208',
        'post_reset_view' => $postResetView,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.208',
        'next_cursor' => 'wp.returning.next.cursor.208',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.208',
        'following_current_source_token' => 'wp.current.source.following.208',
        'following_cursor' => 'wp.returning.following.cursor.208',
        'following_current_view' => $followingView,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'wp-following-current-208',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.208',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.208',
        'recursive_child_generation' => 'wp-recursive-child-current-208',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.208',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.208',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.208',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.208',
        'auto_ack_current_generation_receipts_next203' => true,
        'yield_current_source_token_next206' => 'wp.current.recursive.returning.source.208',
        'yield_current_cursor_next206' => 'wp.returning.current.cursor.208',
        'yield_statement_token_next206' => 'wp.recursive.view.returning.statement.208',
        'close_current_cursor_next208' => 'wp.returning.current.cursor.208',
        'expected_close_current_cursor_next208' => 'wp.returning.current.cursor.208',
        'close_statement_token_next208' => 'wp.recursive.view.returning.close.208',
    ],
);

if (
    $summary['status_next208'] !== 'trigger-recursive-view-returning-current-source-next208-cursor-closed'
    || $summary['next_source_visible_after_current_cursor_close_next208'] !== true
    || $summary['current_cursor_close_plan_next208']['decision'] !== 'publish-next-source-after-current-returning-cursor-close'
    || count($summary['visible_returning_rows_next208']) !== 4
    || $summary['held_next_source_rows_next208'] !== []
) {
    fwrite(STDERR, "wordpress-trigger-recursive-view-returning-current-source-next208 self-test failed\n");
    exit(1);
}

echo "wordpress-trigger-recursive-view-returning-current-source-next208 self-test passed\n";
