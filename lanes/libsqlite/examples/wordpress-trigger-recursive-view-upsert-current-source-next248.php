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
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$view = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-248-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-248-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-upsert-trigger-248',
];
$nextView = $view;
$nextView['source'] = 'main@view-cookie-248-next';
$nextView['trigger_source'] = 'main@trigger-cookie-248-next';
$postResetView = $view;
$postResetView['source'] = 'main@view-cookie-248-post-reset';
$postResetView['trigger_source'] = 'main@trigger-cookie-248-post-reset';
$followingView = $view;
$followingView['source'] = 'main@view-cookie-248-following';
$followingView['trigger_source'] = 'main@trigger-cookie-248-following';

$summary = SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentWhereOutcomeReceipt(
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
        'savepoint' => 'wp_recursive_view_248',
        'cursor_name' => 'wp_recursive_view_returning_cursor_248',
        'admit_next_source' => true,
        'rollback_token' => 'wp.rollback.current.248',
        'post_reset_view' => $postResetView,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.248',
        'next_cursor' => 'wp.returning.next.cursor.248',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.248',
        'following_current_source_token' => 'wp.current.source.following.248',
        'following_cursor' => 'wp.returning.following.cursor.248',
        'following_current_view' => $followingView,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.248',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.248',
        'recursive_child_generation' => 'wp-recursive-child-current-248',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.248',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.248',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.248',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.248',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'wp.current.source.drain.248',
        'current_view_cookie_next209' => 'main@view-cookie-248-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-248-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'current_source_yield_token_next212' => 'wp.current.source.yield.248',
        'current_view_yield_cursor_next212' => 'wp.returning.view.yield.cursor.248',
        'current_trigger_yield_cursor_next212' => 'wp.returning.trigger.yield.cursor.248',
        'auto_ack_current_source_yields_next212' => true,
        'current_source_epoch_next218' => 'wp.current.source.epoch.248',
        'current_view_epoch_next218' => 'wp.returning.view.epoch.cursor.248',
        'current_trigger_epoch_next218' => 'wp.returning.trigger.epoch.cursor.248',
        'auto_ack_current_source_epochs_next218' => true,
        'current_source_ticket_next222' => 'wp.current.source.ticket.248',
        'current_view_source_next222' => 'main@view-cookie-248-current',
        'current_trigger_source_next222' => 'main@trigger-cookie-248-current',
        'auto_ack_current_source_tickets_next222' => true,
        'current_source_cursor_next231' => 'wp.returning.current.cursor.248',
        'current_source_close_token_next231' => 'wp.current.source.close.248',
        'current_view_cookie_next231' => 'main@view-cookie-248-current',
        'current_trigger_cookie_next231' => 'main@trigger-cookie-248-current',
        'auto_ack_current_source_closures_next231' => true,
        'current_source_upsert_token_next234' => 'wp.current.source.upsert.248',
        'current_upsert_view_cookie_next234' => 'main@view-cookie-248-current',
        'current_upsert_trigger_cookie_next234' => 'main@trigger-cookie-248-current',
        'auto_ack_current_source_upserts_next234' => true,
        'current_source_upsert_action_token_next237' => 'wp.current.source.upsert.action.248',
        'current_upsert_action_view_cookie_next237' => 'main@view-cookie-248-current',
        'current_upsert_action_trigger_cookie_next237' => 'main@trigger-cookie-248-current',
        'auto_ack_current_source_upsert_actions_next237' => true,
        'current_source_upsert_close_token_next241' => 'wp.current.source.upsert.close.248',
        'current_source_upsert_generation_next241' => 'main@source-generation-248-current',
        'current_upsert_close_view_cookie_next241' => 'main@view-cookie-248-current',
        'current_upsert_close_trigger_cookie_next241' => 'main@trigger-cookie-248-current',
        'auto_ack_current_source_upsert_closes_next241' => true,
        'current_source_upsert_target_token_next245' => 'wp.current.source.upsert.target.248',
        'current_source_upsert_conflict_target_next245' => ['option_name'],
        'current_source_upsert_excluded_columns_next245' => ['option_value', 'autoload'],
        'current_upsert_target_view_cookie_next245' => 'main@view-cookie-248-current',
        'current_upsert_target_trigger_cookie_next245' => 'main@trigger-cookie-248-current',
        'auto_ack_current_source_upsert_targets_next245' => true,
        'current_source_upsert_where_token_next248' => 'wp.current.source.upsert.where.248',
        'current_source_upsert_where_columns_next248' => ['option_value', 'autoload'],
        'auto_ack_current_source_upsert_where_next248' => true,
    ],
);

if (
    $summary['status_next248'] !== 'trigger-recursive-view-upsert-current-source-next248-where-released'
    || $summary['current_source_upsert_where_plan_next248']['decision'] !== 'publish-next-source-after-current-recursive-view-upsert-where'
    || $summary['current_source_upsert_where_outcomes_next248'] !== [true, true]
    || array_column($summary['visible_returning_payloads_next248'], 'name') !== ['blogdescription_child', 'template_child', 'home', 'next_plugin']
    || $summary['held_next_source_rows_next248'] !== []
) {
    fwrite(STDERR, "wordpress-trigger-recursive-view-upsert-current-source-next248 self-test failed\n");
    exit(1);
}

echo "wordpress-trigger-recursive-view-upsert-current-source-next248 self-test passed\n";
