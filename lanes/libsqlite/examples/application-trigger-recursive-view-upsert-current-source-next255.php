<?php

declare(strict_types=1);

foreach (glob(dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext*Plan.php') ?: [] as $planFile) {
    require_once $planFile;
}
foreach ([
    'SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php',
    'SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php',
    'SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php',
    'SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php',
    'SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php',
    'SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php',
] as $planFile) {
    require_once dirname(__DIR__) . '/src/' . $planFile;
}

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$view = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-255-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-255-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-upsert-returning-drain-255',
];
$nextView = $view;
$nextView['source'] = 'main@view-cookie-255-next';
$nextView['trigger_source'] = 'main@trigger-cookie-255-next';
$postResetView = $view;
$postResetView['source'] = 'main@view-cookie-255-post-reset';
$postResetView['trigger_source'] = 'main@trigger-cookie-255-post-reset';
$followingView = $view;
$followingView['source'] = 'main@view-cookie-255-following';
$followingView['trigger_source'] = 'main@trigger-cookie-255-following';

$summary = SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentSourceUpsertReturningDrain(
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
        ['expr' => 'spawn_child', 'as' => 'spawn_child'],
    ],
    [
        'key' => 'key_name',
        'savepoint' => 'app_recursive_view_255',
        'cursor_name' => 'app_recursive_view_returning_cursor_255',
        'admit_next_source' => true,
        'rollback_token' => 'app.rollback.current.255',
        'reset_generation' => 'app-current-reset-255',
        'post_reset_current_source_token' => 'app.current.source.postreset.255',
        'post_reset_cursor' => 'app.returning.postreset.cursor.255',
        'post_reset_view' => $postResetView,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'base_url', 'value' => 'https://fresh.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'routing_rules', 'value' => 'fresh-rules', 'load_policy_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'app.next.source.255',
        'next_cursor' => 'app.returning.next.cursor.255',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'app.returning.next.cursor.255',
        'following_current_source_token' => 'app.current.source.following.255',
        'following_cursor' => 'app.returning.following.cursor.255',
        'following_current_view' => $followingView,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'app_summary', 'value' => 'after-next', 'load_policy_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'theme_style_key', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'app-following-current-255',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'app.current.source.recursive.child.255',
        'recursive_child_cursor' => 'app.returning.recursive.child.cursor.255',
        'recursive_child_generation' => 'app-recursive-child-current-255',
        'current_generation_next203' => 'app.current.recursive.returning.generation.255',
        'expected_current_generation_next203' => 'app.current.recursive.returning.generation.255',
        'current_handoff_cursor_next203' => 'app.returning.current.handoff.cursor.255',
        'current_generation_commit_marker_next203' => 'app.current.recursive.returning.commit.255',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'app.current.source.drain.255',
        'current_view_cookie_next209' => 'main@view-cookie-255-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-255-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'current_source_yield_token_next212' => 'app.current.source.yield.255',
        'current_view_yield_cursor_next212' => 'app.returning.view.yield.cursor.255',
        'current_trigger_yield_cursor_next212' => 'app.returning.trigger.yield.cursor.255',
        'auto_ack_current_source_yields_next212' => true,
        'current_source_epoch_next218' => 'app.current.source.epoch.255',
        'current_view_epoch_next218' => 'app.returning.view.epoch.cursor.255',
        'current_trigger_epoch_next218' => 'app.returning.trigger.epoch.cursor.255',
        'auto_ack_current_source_epochs_next218' => true,
        'current_source_ticket_next222' => 'app.current.source.ticket.255',
        'current_view_source_next222' => 'main@view-cookie-255-current',
        'current_trigger_source_next222' => 'main@trigger-cookie-255-current',
        'auto_ack_current_source_tickets_next222' => true,
        'current_source_cursor_source_close' => 'app.returning.current.cursor.255',
        'current_source_close_token_source_close' => 'app.current.source.close.255',
        'current_view_cookie_source_close' => 'main@view-cookie-255-current',
        'current_trigger_cookie_source_close' => 'main@trigger-cookie-255-current',
        'auto_ack_current_source_closures_source_close' => true,
        'current_source_upsert_cursor_next240' => 'app.upsert.current.cursor.255',
        'current_view_upsert_cookie_next240' => 'main@view-cookie-255-current',
        'current_trigger_upsert_cookie_next240' => 'main@trigger-cookie-255-current',
        'upsert_conflict_columns_next240' => ['name'],
        'auto_ack_current_source_upserts_next240' => true,
        'current_source_view_cookie_next243' => 'main@view-cookie-255-current',
        'expected_current_source_view_cookie_next243' => 'main@view-cookie-255-current',
        'current_source_trigger_cookie_next243' => 'main@trigger-cookie-255-current',
        'expected_current_source_trigger_cookie_next243' => 'main@trigger-cookie-255-current',
        'next_source_view_cookie_next243' => 'main@view-cookie-255-next',
        'upsert_source_cursor_next243' => 'app.upsert.source.cursor.255',
        'current_source_conflict_image_token_next246' => 'app.current.source.conflict.image.255',
        'upsert_conflict_columns_next246' => ['name'],
        'upsert_excluded_columns_next246' => ['value', 'spawn_child'],
        'auto_ack_current_source_conflict_images_next246' => true,
        'current_source_assignment_token_next249' => 'app.current.source.assignment.255',
        'upsert_assignment_columns_next249' => ['value', 'spawn_child'],
        'auto_ack_current_source_assignments_next249' => true,
        'current_source_upsert_where_token_next252' => 'app.current.source.upsert.where.255',
        'auto_ack_current_source_upsert_where_next252' => true,
        'current_source_returning_cursor_next255' => 'app.returning.current.upsert.cursor.255',
        'required_current_source_returning_aliases_next255' => ['name', 'value', 'event_name', 'spawn_child'],
        'auto_ack_current_source_returning_next255' => true,
    ],
);

if (
    $summary['status_next255'] !== 'trigger-recursive-view-upsert-current-source-next255-returning-released'
    || $summary['current_source_returning_drain_plan_next255']['decision'] !== 'publish-next-source-after-current-upsert-returning-drain'
    || $summary['visible_row_count_next255'] !== 4
    || $summary['held_next_row_count_next255'] !== 0
) {
    fwrite(STDERR, "application-trigger-recursive-view-upsert-current-source-next255 self-test failed\n");
    exit(1);
}

echo "application-trigger-recursive-view-upsert-current-source-next255 self-test passed\n";
