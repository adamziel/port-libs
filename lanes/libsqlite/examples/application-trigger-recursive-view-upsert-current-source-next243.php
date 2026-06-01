<?php

declare(strict_types=1);

foreach (glob(__DIR__ . '/../src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext*.php') ?: [] as $file) {
    require_once $file;
}
require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$view = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-243-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-243-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-upsert-243',
];
$nextView = $view;
$nextView['source'] = 'main@view-cookie-243-next';
$nextView['trigger_source'] = 'main@trigger-cookie-243-next';
$postResetView = $view;
$postResetView['source'] = 'main@view-cookie-243-post-reset';
$postResetView['trigger_source'] = 'main@trigger-cookie-243-post-reset';
$followingView = $view;
$followingView['source'] = 'main@view-cookie-243-following';
$followingView['trigger_source'] = 'main@trigger-cookie-243-following';

$summary = SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentSourceReady(
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
        'savepoint' => 'app_recursive_view_243',
        'cursor_name' => 'app_recursive_view_returning_cursor_243',
        'admit_next_source' => true,
        'rollback_token' => 'app.rollback.current.243',
        'reset_generation' => 'app-current-reset-243',
        'post_reset_current_source_token' => 'app.current.source.postreset.243',
        'post_reset_cursor' => 'app.returning.postreset.cursor.243',
        'post_reset_view' => $postResetView,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'base_url', 'value' => 'https://fresh.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'routing_rules', 'value' => 'fresh-rules', 'load_policy_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'app.next.source.243',
        'next_cursor' => 'app.returning.next.cursor.243',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'app.returning.next.cursor.243',
        'following_current_source_token' => 'app.current.source.following.243',
        'following_cursor' => 'app.returning.following.cursor.243',
        'following_current_view' => $followingView,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'app_summary', 'value' => 'after-next', 'load_policy_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'theme_style_key', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'app-following-current-243',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'app.current.source.recursive.child.243',
        'recursive_child_cursor' => 'app.returning.recursive.child.cursor.243',
        'recursive_child_generation' => 'app-recursive-child-current-243',
        'current_generation_next203' => 'app.current.recursive.returning.generation.243',
        'expected_current_generation_next203' => 'app.current.recursive.returning.generation.243',
        'current_handoff_cursor_next203' => 'app.returning.current.handoff.cursor.243',
        'current_generation_commit_marker_next203' => 'app.current.recursive.returning.commit.243',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'app.current.source.drain.243',
        'current_view_cookie_next209' => 'main@view-cookie-243-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-243-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'current_source_yield_token_next212' => 'app.current.source.yield.243',
        'current_view_yield_cursor_next212' => 'app.returning.view.yield.cursor.243',
        'current_trigger_yield_cursor_next212' => 'app.returning.trigger.yield.cursor.243',
        'auto_ack_current_source_yields_next212' => true,
        'current_source_epoch_next218' => 'app.current.source.epoch.243',
        'current_view_epoch_next218' => 'app.returning.view.epoch.cursor.243',
        'current_trigger_epoch_next218' => 'app.returning.trigger.epoch.cursor.243',
        'auto_ack_current_source_epochs_next218' => true,
        'current_source_ticket_next222' => 'app.current.source.ticket.243',
        'current_view_source_next222' => 'main@view-cookie-243-current',
        'current_trigger_source_next222' => 'main@trigger-cookie-243-current',
        'auto_ack_current_source_tickets_next222' => true,
        'current_source_cursor_source_close' => 'app.returning.current.cursor.243',
        'current_source_close_token_source_close' => 'app.current.source.close.243',
        'current_view_cookie_source_close' => 'main@view-cookie-243-current',
        'current_trigger_cookie_source_close' => 'main@trigger-cookie-243-current',
        'auto_ack_current_source_closures_source_close' => true,
        'current_source_upsert_cursor_next240' => 'app.upsert.current.cursor.243',
        'current_view_upsert_cookie_next240' => 'main@view-cookie-243-current',
        'current_trigger_upsert_cookie_next240' => 'main@trigger-cookie-243-current',
        'upsert_conflict_columns_next240' => ['name'],
        'auto_ack_current_source_upserts_next240' => true,
        'current_source_view_cookie_next243' => 'main@view-cookie-243-current',
        'expected_current_source_view_cookie_next243' => 'main@view-cookie-243-current',
        'current_source_trigger_cookie_next243' => 'main@trigger-cookie-243-current',
        'expected_current_source_trigger_cookie_next243' => 'main@trigger-cookie-243-current',
        'next_source_view_cookie_next243' => 'main@view-cookie-243-next',
        'upsert_source_cursor_next243' => 'app.upsert.source.cursor.243',
    ],
);

if (
    $summary['status_next243'] !== 'trigger-recursive-view-upsert-current-source-next243-source-released'
    || $summary['upsert_current_source_plan_next243']['decision'] !== 'publish-next-source-after-current-view-upsert-source-match'
    || array_column($summary['visible_returning_payloads_next243'], 'name') !== ['app_summary_child', 'template_child', 'landing_url', 'next_module']
    || $summary['held_next_source_rows_next243'] !== []
) {
    fwrite(STDERR, "application-trigger-recursive-view-upsert-current-source-next243 self-test failed\n");
    exit(1);
}

echo "application-trigger-recursive-view-upsert-current-source-next243 self-test passed\n";
