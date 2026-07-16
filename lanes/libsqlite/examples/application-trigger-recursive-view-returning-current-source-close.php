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
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-source-close-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-source-close-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-source-close',
];
$nextView = $view;
$nextView['source'] = 'main@view-cookie-source-close-next';
$nextView['trigger_source'] = 'main@trigger-cookie-source-close-next';
$postResetView = $view;
$postResetView['source'] = 'main@view-cookie-source-close-post-reset';
$postResetView['trigger_source'] = 'main@trigger-cookie-source-close-post-reset';
$followingView = $view;
$followingView['source'] = 'main@view-cookie-source-close-following';
$followingView['trigger_source'] = 'main@trigger-cookie-source-close-following';

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentSourceCursorClose(
    [
        ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes'],
        ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes'],
    ],
    [
        ['import_id' => 10, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
        ['import_id' => 11, 'name' => 'current_module', 'value' => 'enabled', 'load_policy_flag' => 'no', 'spawn_child' => true],
    ],
    [
        ['import_id' => 20, 'name' => 'landing_url', 'value' => 'https://next.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
        ['import_id' => 21, 'name' => 'next_module', 'value' => 'active', 'load_policy_flag' => 'no', 'spawn_child' => false],
    ],
    $view,
    $nextView,
    [
        ['expr' => 'new.key_name', 'as' => 'name'],
        ['expr' => 'new.key_value', 'as' => 'value'],
        ['expr' => 'event', 'as' => 'event_name'],
        ['expr' => 'depth', 'as' => 'depth_value'],
        ['expr' => 'ordinal', 'as' => 'ordinal_value'],
        ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
        ['expr' => 'spawn_child', 'as' => 'spawn_child'],
    ],
    [
        'key' => 'key_name',
        'savepoint' => 'app_recursive_view_source_close',
        'cursor_name' => 'app_recursive_view_returning_cursor_source_close',
        'admit_next_source' => true,
        'rollback_token' => 'app.rollback.current.source.close',
        'reset_generation' => 'app-current-reset-source-close',
        'post_reset_current_source_token' => 'app.current.source.postreset.source.close',
        'post_reset_cursor' => 'app.returning.postreset.cursor.source.close',
        'post_reset_view' => $postResetView,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'base_url', 'value' => 'https://fresh.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'routing_rules', 'value' => 'fresh-rules', 'load_policy_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'app.next.source.source.close',
        'next_cursor' => 'app.returning.next.cursor.source.close',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'app.returning.next.cursor.source.close',
        'following_current_source_token' => 'app.current.source.following.source.close',
        'following_cursor' => 'app.returning.following.cursor.source.close',
        'following_current_view' => $followingView,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'app_summary', 'value' => 'after-next', 'load_policy_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'theme_style_key', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'app-following-current-source-close',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'app.current.source.recursive.child.source.close',
        'recursive_child_cursor' => 'app.returning.recursive.child.cursor.source.close',
        'recursive_child_generation' => 'app-recursive-child-current-source-close',
        'current_generation_next203' => 'app.current.recursive.returning.generation.source.close',
        'expected_current_generation_next203' => 'app.current.recursive.returning.generation.source.close',
        'current_handoff_cursor_next203' => 'app.returning.current.handoff.cursor.source.close',
        'current_generation_commit_marker_next203' => 'app.current.recursive.returning.commit.source.close',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'app.current.source.drain.source.close',
        'current_view_cookie_next209' => 'main@view-cookie-source-close-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-source-close-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'current_source_yield_token_next212' => 'app.current.source.yield.source.close',
        'current_view_yield_cursor_next212' => 'app.returning.view.yield.cursor.source.close',
        'current_trigger_yield_cursor_next212' => 'app.returning.trigger.yield.cursor.source.close',
        'auto_ack_current_source_yields_next212' => true,
        'current_source_epoch_next218' => 'app.current.source.epoch.source.close',
        'current_view_epoch_next218' => 'app.returning.view.epoch.cursor.source.close',
        'current_trigger_epoch_next218' => 'app.returning.trigger.epoch.cursor.source.close',
        'auto_ack_current_source_epochs_next218' => true,
        'current_source_ticket' => 'app.current.source.ticket.source.close',
        'current_view_source_ticket' => 'main@view-cookie-source-close-current',
        'current_trigger_source_ticket' => 'main@trigger-cookie-source-close-current',
        'auto_ack_current_source_tickets' => true,
        'current_source_cursor_source_close' => 'app.returning.current.cursor.source.close',
        'current_source_close_token_source_close' => 'app.current.source.close.source.close',
        'current_view_cookie_source_close' => 'main@view-cookie-source-close-current',
        'current_trigger_cookie_source_close' => 'main@trigger-cookie-source-close-current',
        'auto_ack_current_source_closures_source_close' => true,
    ],
);

if (
    $summary['status_source_close'] !== 'trigger-recursive-view-returning-current-source-source_close-cursor-close-released'
    || $summary['current_source_close_plan_source_close']['decision'] !== 'publish-next-source-after-current-returning-cursor-close'
    || array_column($summary['visible_returning_payloads_source_close'], 'name') !== ['app_summary_child', 'template_child', 'landing_url', 'next_module']
    || $summary['held_next_source_rows_source_close'] !== []
) {
    fwrite(STDERR, "application-trigger-recursive-view-returning-current-source-source_close self-test failed\n");
    exit(1);
}

echo "application-trigger-recursive-view-returning-current-source-source_close self-test passed\n";
