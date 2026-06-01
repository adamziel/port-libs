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

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$view = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-237-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-237-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-upsert-trigger-237',
];
$nextView = $view;
$nextView['source'] = 'main@view-cookie-237-next';
$nextView['trigger_source'] = 'main@trigger-cookie-237-next';
$postResetView = $view;
$postResetView['source'] = 'main@view-cookie-237-post-reset';
$postResetView['trigger_source'] = 'main@trigger-cookie-237-post-reset';
$followingView = $view;
$followingView['source'] = 'main@view-cookie-237-following';
$followingView['trigger_source'] = 'main@trigger-cookie-237-following';

$summary = SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentActionReceipt(
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
        ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
        ['expr' => 'spawn_child', 'as' => 'spawn_child'],
    ],
    [
        'savepoint' => 'app_recursive_view_237',
        'cursor_name' => 'app_recursive_view_returning_cursor_237',
        'admit_next_source' => true,
        'rollback_token' => 'app.rollback.current.237',
        'post_reset_view' => $postResetView,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'base_url', 'value' => 'https://fresh.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'routing_rules', 'value' => 'fresh-rules', 'load_policy_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'app.next.source.237',
        'next_cursor' => 'app.returning.next.cursor.237',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'app.returning.next.cursor.237',
        'following_current_source_token' => 'app.current.source.following.237',
        'following_cursor' => 'app.returning.following.cursor.237',
        'following_current_view' => $followingView,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'app_summary', 'value' => 'after-next', 'load_policy_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'theme_style_key', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => true],
        ],
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'app.current.source.recursive.child.237',
        'recursive_child_cursor' => 'app.returning.recursive.child.cursor.237',
        'recursive_child_generation' => 'app-recursive-child-current-237',
        'current_generation_next203' => 'app.current.recursive.returning.generation.237',
        'expected_current_generation_next203' => 'app.current.recursive.returning.generation.237',
        'current_handoff_cursor_next203' => 'app.returning.current.handoff.cursor.237',
        'current_generation_commit_marker_next203' => 'app.current.recursive.returning.commit.237',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'app.current.source.drain.237',
        'current_view_cookie_next209' => 'main@view-cookie-237-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-237-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'current_source_yield_token_next212' => 'app.current.source.yield.237',
        'current_view_yield_cursor_next212' => 'app.returning.view.yield.cursor.237',
        'current_trigger_yield_cursor_next212' => 'app.returning.trigger.yield.cursor.237',
        'auto_ack_current_source_yields_next212' => true,
        'current_source_epoch_next218' => 'app.current.source.epoch.237',
        'current_view_epoch_next218' => 'app.returning.view.epoch.cursor.237',
        'current_trigger_epoch_next218' => 'app.returning.trigger.epoch.cursor.237',
        'auto_ack_current_source_epochs_next218' => true,
        'current_source_ticket_next222' => 'app.current.source.ticket.237',
        'current_view_source_next222' => 'main@view-cookie-237-current',
        'current_trigger_source_next222' => 'main@trigger-cookie-237-current',
        'auto_ack_current_source_tickets_next222' => true,
        'current_source_cursor_source_close' => 'app.returning.current.cursor.237',
        'current_source_close_token_source_close' => 'app.current.source.close.237',
        'current_view_cookie_source_close' => 'main@view-cookie-237-current',
        'current_trigger_cookie_source_close' => 'main@trigger-cookie-237-current',
        'auto_ack_current_source_closures_source_close' => true,
        'current_source_upsert_token_next234' => 'app.current.source.upsert.237',
        'current_upsert_view_cookie_next234' => 'main@view-cookie-237-current',
        'current_upsert_trigger_cookie_next234' => 'main@trigger-cookie-237-current',
        'auto_ack_current_source_upserts_next234' => true,
        'current_source_upsert_action_token_next237' => 'app.current.source.upsert.action.237',
        'current_upsert_action_view_cookie_next237' => 'main@view-cookie-237-current',
        'current_upsert_action_trigger_cookie_next237' => 'main@trigger-cookie-237-current',
        'auto_ack_current_source_upsert_actions_next237' => true,
    ],
);

if (
    $summary['status_next237'] !== 'trigger-recursive-view-upsert-current-source-next237-actions-released'
    || $summary['current_source_upsert_action_plan_next237']['decision'] !== 'publish-next-source-after-current-recursive-view-upsert-actions'
    || array_column($summary['visible_returning_payloads_next237'], 'name') !== ['app_summary_child', 'template_child', 'landing_url', 'next_module']
    || array_column($summary['current_source_upsert_actions_next237'], 'action') !== ['insert-recursive', 'insert-recursive']
    || $summary['held_next_source_rows_next237'] !== []
) {
    fwrite(STDERR, "application-trigger-recursive-view-upsert-current-source-next237 self-test failed\n");
    exit(1);
}

echo "application-trigger-recursive-view-upsert-current-source-next237 self-test passed\n";
