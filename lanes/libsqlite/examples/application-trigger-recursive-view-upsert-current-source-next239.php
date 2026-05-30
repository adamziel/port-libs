<?php

declare(strict_types=1);

$planFiles = glob(dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext*Plan.php') ?: [];
usort($planFiles, 'strnatcmp');
foreach ($planFiles as $planFile) {
    require_once $planFile;
}
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$view = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-239-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-239-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-upsert-239',
];
$nextView = $view;
$nextView['source'] = 'main@view-cookie-239-next';
$nextView['trigger_source'] = 'main@trigger-cookie-239-next';
$postResetView = $view;
$postResetView['source'] = 'main@view-cookie-239-post-reset';
$postResetView['trigger_source'] = 'main@trigger-cookie-239-post-reset';
$followingView = $view;
$followingView['source'] = 'main@view-cookie-239-following';
$followingView['trigger_source'] = 'main@trigger-cookie-239-following';

$summary = SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentTargetReceipt(
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
        'savepoint' => 'wp_recursive_view_239',
        'cursor_name' => 'wp_recursive_view_returning_cursor_239',
        'admit_next_source' => true,
        'rollback_token' => 'wp.rollback.current.239',
        'reset_generation' => 'wp-current-reset-239',
        'post_reset_current_source_token' => 'wp.current.source.postreset.239',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.239',
        'post_reset_view' => $postResetView,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.239',
        'next_cursor' => 'wp.returning.next.cursor.239',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.239',
        'following_current_source_token' => 'wp.current.source.following.239',
        'following_cursor' => 'wp.returning.following.cursor.239',
        'following_current_view' => $followingView,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'wp-following-current-239',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.239',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.239',
        'recursive_child_generation' => 'wp-recursive-child-current-239',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.239',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.239',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.239',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.239',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'wp.current.source.drain.239',
        'current_view_cookie_next209' => 'main@view-cookie-239-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-239-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'current_source_yield_token_next212' => 'wp.current.source.yield.239',
        'current_view_yield_cursor_next212' => 'wp.returning.view.yield.cursor.239',
        'current_trigger_yield_cursor_next212' => 'wp.returning.trigger.yield.cursor.239',
        'auto_ack_current_source_yields_next212' => true,
        'current_source_epoch_next218' => 'wp.current.source.epoch.239',
        'current_view_epoch_next218' => 'wp.returning.view.epoch.cursor.239',
        'current_trigger_epoch_next218' => 'wp.returning.trigger.epoch.cursor.239',
        'auto_ack_current_source_epochs_next218' => true,
        'current_source_ticket_next222' => 'wp.current.source.ticket.239',
        'current_view_source_next222' => 'main@view-cookie-239-current',
        'current_trigger_source_next222' => 'main@trigger-cookie-239-current',
        'auto_ack_current_source_tickets_next222' => true,
        'current_source_cursor_source_close' => 'wp.returning.current.cursor.239',
        'current_source_close_token_source_close' => 'wp.current.source.close.239',
        'current_view_cookie_source_close' => 'main@view-cookie-239-current',
        'current_trigger_cookie_source_close' => 'main@trigger-cookie-239-current',
        'auto_ack_current_source_closures_source_close' => true,
        'current_source_upsert_target_next239' => 'option_name',
        'current_source_upsert_policy_next239' => 'do-update-returning',
        'current_source_upsert_cursor_next239' => 'wp.returning.upsert.cursor.239',
        'current_source_upsert_generation_next239' => 'wp.current.source.upsert.generation.239',
        'auto_ack_current_source_upsert_targets_next239' => true,
    ],
);

if (
    $summary['status_next239'] !== 'trigger-recursive-view-upsert-current-source-next239-targets-released'
    || $summary['current_source_upsert_plan_next239']['decision'] !== 'publish-next-source-after-current-upsert-targets'
    || array_column($summary['visible_returning_payloads_next239'], 'name') !== ['blogdescription_child', 'template_child', 'home', 'next_plugin']
    || $summary['held_next_source_rows_next239'] !== []
) {
    fwrite(STDERR, "application-trigger-recursive-view-upsert-current-source-next239 self-test failed\n");
    exit(1);
}

echo "application-trigger-recursive-view-upsert-current-source-next239 self-test passed\n";
