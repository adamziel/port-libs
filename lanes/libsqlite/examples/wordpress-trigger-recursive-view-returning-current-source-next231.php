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
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$view = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-231-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-231-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-231',
];
$nextView = $view;
$nextView['source'] = 'main@view-cookie-231-next';
$nextView['trigger_source'] = 'main@trigger-cookie-231-next';
$postResetView = $view;
$postResetView['source'] = 'main@view-cookie-231-post-reset';
$postResetView['trigger_source'] = 'main@trigger-cookie-231-post-reset';
$followingView = $view;
$followingView['source'] = 'main@view-cookie-231-following';
$followingView['trigger_source'] = 'main@trigger-cookie-231-following';

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext231(
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
    $view,
    $nextView,
    [
        ['expr' => 'new.option_name', 'as' => 'name'],
        ['expr' => 'new.option_value', 'as' => 'value'],
        ['expr' => 'event', 'as' => 'event_name'],
        ['expr' => 'depth', 'as' => 'depth_value'],
        ['expr' => 'ordinal', 'as' => 'ordinal_value'],
        ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
        ['expr' => 'spawn_child', 'as' => 'spawn_child'],
    ],
    [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_231',
        'cursor_name' => 'wp_recursive_view_returning_cursor_231',
        'admit_next_source' => true,
        'rollback_token' => 'wp.rollback.current.231',
        'reset_generation' => 'wp-current-reset-231',
        'post_reset_current_source_token' => 'wp.current.source.postreset.231',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.231',
        'post_reset_view' => $postResetView,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.231',
        'next_cursor' => 'wp.returning.next.cursor.231',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.231',
        'following_current_source_token' => 'wp.current.source.following.231',
        'following_cursor' => 'wp.returning.following.cursor.231',
        'following_current_view' => $followingView,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'wp-following-current-231',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.231',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.231',
        'recursive_child_generation' => 'wp-recursive-child-current-231',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.231',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.231',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.231',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.231',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'wp.current.source.drain.231',
        'current_view_cookie_next209' => 'main@view-cookie-231-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-231-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'current_source_yield_token_next212' => 'wp.current.source.yield.231',
        'current_view_yield_cursor_next212' => 'wp.returning.view.yield.cursor.231',
        'current_trigger_yield_cursor_next212' => 'wp.returning.trigger.yield.cursor.231',
        'auto_ack_current_source_yields_next212' => true,
        'current_source_epoch_next218' => 'wp.current.source.epoch.231',
        'current_view_epoch_next218' => 'wp.returning.view.epoch.cursor.231',
        'current_trigger_epoch_next218' => 'wp.returning.trigger.epoch.cursor.231',
        'auto_ack_current_source_epochs_next218' => true,
        'current_source_ticket_next222' => 'wp.current.source.ticket.231',
        'current_view_source_next222' => 'main@view-cookie-231-current',
        'current_trigger_source_next222' => 'main@trigger-cookie-231-current',
        'auto_ack_current_source_tickets_next222' => true,
        'current_source_cursor_next231' => 'wp.returning.current.cursor.231',
        'current_source_close_token_next231' => 'wp.current.source.close.231',
        'current_view_cookie_next231' => 'main@view-cookie-231-current',
        'current_trigger_cookie_next231' => 'main@trigger-cookie-231-current',
        'auto_ack_current_source_closures_next231' => true,
    ],
);

if (
    $summary['status_next231'] !== 'trigger-recursive-view-returning-current-source-next231-cursor-close-released'
    || $summary['current_source_close_plan_next231']['decision'] !== 'publish-next-source-after-current-returning-cursor-close'
    || array_column($summary['visible_returning_payloads_next231'], 'name') !== ['blogdescription_child', 'template_child', 'home', 'next_plugin']
    || $summary['held_next_source_rows_next231'] !== []
) {
    fwrite(STDERR, "wordpress-trigger-recursive-view-returning-current-source-next231 self-test failed\n");
    exit(1);
}

echo "wordpress-trigger-recursive-view-returning-current-source-next231 self-test passed\n";
