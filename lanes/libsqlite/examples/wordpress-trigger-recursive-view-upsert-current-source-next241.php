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
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$view = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-241-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-241-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-upsert-trigger-241',
];
$nextView = $view;
$nextView['source'] = 'main@view-cookie-241-next';
$nextView['trigger_source'] = 'main@trigger-cookie-241-next';
$postResetView = $view;
$postResetView['source'] = 'main@view-cookie-241-post-reset';
$postResetView['trigger_source'] = 'main@trigger-cookie-241-post-reset';
$followingView = $view;
$followingView['source'] = 'main@view-cookie-241-following';
$followingView['trigger_source'] = 'main@trigger-cookie-241-following';

$summary = SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentCloseReceipt(
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
        ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
        ['expr' => 'spawn_child', 'as' => 'spawn_child'],
    ],
    [
        'savepoint' => 'wp_recursive_view_241',
        'cursor_name' => 'wp_recursive_view_returning_cursor_241',
        'admit_next_source' => true,
        'rollback_token' => 'wp.rollback.current.241',
        'post_reset_view' => $postResetView,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.241',
        'next_cursor' => 'wp.returning.next.cursor.241',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.241',
        'following_current_source_token' => 'wp.current.source.following.241',
        'following_cursor' => 'wp.returning.following.cursor.241',
        'following_current_view' => $followingView,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.241',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.241',
        'recursive_child_generation' => 'wp-recursive-child-current-241',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.241',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.241',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.241',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.241',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'wp.current.source.drain.241',
        'current_view_cookie_next209' => 'main@view-cookie-241-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-241-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'current_source_yield_token_next212' => 'wp.current.source.yield.241',
        'current_view_yield_cursor_next212' => 'wp.returning.view.yield.cursor.241',
        'current_trigger_yield_cursor_next212' => 'wp.returning.trigger.yield.cursor.241',
        'auto_ack_current_source_yields_next212' => true,
        'current_source_epoch_next218' => 'wp.current.source.epoch.241',
        'current_view_epoch_next218' => 'wp.returning.view.epoch.cursor.241',
        'current_trigger_epoch_next218' => 'wp.returning.trigger.epoch.cursor.241',
        'auto_ack_current_source_epochs_next218' => true,
        'current_source_ticket_next222' => 'wp.current.source.ticket.241',
        'current_view_source_next222' => 'main@view-cookie-241-current',
        'current_trigger_source_next222' => 'main@trigger-cookie-241-current',
        'auto_ack_current_source_tickets_next222' => true,
        'current_source_cursor_next231' => 'wp.returning.current.cursor.241',
        'current_source_close_token_next231' => 'wp.current.source.close.241',
        'current_view_cookie_next231' => 'main@view-cookie-241-current',
        'current_trigger_cookie_next231' => 'main@trigger-cookie-241-current',
        'auto_ack_current_source_closures_next231' => true,
        'current_source_upsert_token_next234' => 'wp.current.source.upsert.241',
        'current_upsert_view_cookie_next234' => 'main@view-cookie-241-current',
        'current_upsert_trigger_cookie_next234' => 'main@trigger-cookie-241-current',
        'auto_ack_current_source_upserts_next234' => true,
        'current_source_upsert_action_token_next237' => 'wp.current.source.upsert.action.241',
        'current_upsert_action_view_cookie_next237' => 'main@view-cookie-241-current',
        'current_upsert_action_trigger_cookie_next237' => 'main@trigger-cookie-241-current',
        'auto_ack_current_source_upsert_actions_next237' => true,
        'current_source_upsert_close_token_next241' => 'wp.current.source.upsert.close.241',
        'current_source_upsert_generation_next241' => 'main@source-generation-241-current',
        'current_upsert_close_view_cookie_next241' => 'main@view-cookie-241-current',
        'current_upsert_close_trigger_cookie_next241' => 'main@trigger-cookie-241-current',
        'auto_ack_current_source_upsert_closes_next241' => true,
    ],
);

if (
    $summary['status_next241'] !== 'trigger-recursive-view-upsert-current-source-next241-close-released'
    || $summary['current_source_upsert_close_plan_next241']['decision'] !== 'publish-next-source-after-current-recursive-view-upsert-close'
    || array_column($summary['visible_returning_payloads_next241'], 'name') !== ['blogdescription_child', 'template_child', 'home', 'next_plugin']
    || $summary['current_source_upsert_generation_next241'] !== 'main@source-generation-241-current'
    || $summary['held_next_source_rows_next241'] !== []
) {
    fwrite(STDERR, "wordpress-trigger-recursive-view-upsert-current-source-next241 self-test failed\n");
    exit(1);
}

echo "wordpress-trigger-recursive-view-upsert-current-source-next241 self-test passed\n";
