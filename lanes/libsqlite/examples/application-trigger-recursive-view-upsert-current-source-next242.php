<?php

declare(strict_types=1);

$returningPlanFiles = glob(dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext*Plan.php') ?: [];
usort($returningPlanFiles, 'strnatcmp');
foreach ($returningPlanFiles as $planFile) {
    require_once $planFile;
}
foreach ([
    'SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php',
    'SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php',
] as $planFile) {
    require_once dirname(__DIR__) . '/src/' . $planFile;
}

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$view = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-242-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-242-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-upsert-242',
];
$nextView = $view;
$nextView['source'] = 'main@view-cookie-242-next';
$nextView['trigger_source'] = 'main@trigger-cookie-242-next';
$postResetView = $view;
$postResetView['source'] = 'main@view-cookie-242-post-reset';
$postResetView['trigger_source'] = 'main@trigger-cookie-242-post-reset';
$followingView = $view;
$followingView['source'] = 'main@view-cookie-242-following';
$followingView['trigger_source'] = 'main@trigger-cookie-242-following';

$summary = SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentStatementEpochReceipt(
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
        'savepoint' => 'app_recursive_view_242',
        'cursor_name' => 'app_recursive_view_returning_cursor_242',
        'admit_next_source' => true,
        'rollback_token' => 'app.rollback.current.242',
        'reset_generation' => 'app-current-reset-242',
        'post_reset_current_source_token' => 'app.current.source.postreset.242',
        'post_reset_cursor' => 'app.returning.postreset.cursor.242',
        'post_reset_view' => $postResetView,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'base_url', 'value' => 'https://fresh.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'routing_rules', 'value' => 'fresh-rules', 'load_policy_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'app.next.source.242',
        'next_cursor' => 'app.returning.next.cursor.242',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'app.returning.next.cursor.242',
        'following_current_source_token' => 'app.current.source.following.242',
        'following_cursor' => 'app.returning.following.cursor.242',
        'following_current_view' => $followingView,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'app_summary', 'value' => 'after-next', 'load_policy_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'theme_style_key', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'app-following-current-242',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'app.current.source.recursive.child.242',
        'recursive_child_cursor' => 'app.returning.recursive.child.cursor.242',
        'recursive_child_generation' => 'app-recursive-child-current-242',
        'current_generation_next203' => 'app.current.recursive.returning.generation.242',
        'expected_current_generation_next203' => 'app.current.recursive.returning.generation.242',
        'current_handoff_cursor_next203' => 'app.returning.current.handoff.cursor.242',
        'current_generation_commit_marker_next203' => 'app.current.recursive.returning.commit.242',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'app.current.source.drain.242',
        'current_view_cookie_next209' => 'main@view-cookie-242-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-242-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'current_source_yield_token_next212' => 'app.current.source.yield.242',
        'current_view_yield_cursor_next212' => 'app.returning.view.yield.cursor.242',
        'current_trigger_yield_cursor_next212' => 'app.returning.trigger.yield.cursor.242',
        'auto_ack_current_source_yields_next212' => true,
        'current_source_epoch_next218' => 'app.current.source.epoch.242',
        'current_view_epoch_next218' => 'app.returning.view.epoch.cursor.242',
        'current_trigger_epoch_next218' => 'app.returning.trigger.epoch.cursor.242',
        'auto_ack_current_source_epochs_next218' => true,
        'current_source_ticket_next222' => 'app.current.source.ticket.242',
        'current_view_source_next222' => 'main@view-cookie-242-current',
        'current_trigger_source_next222' => 'main@trigger-cookie-242-current',
        'auto_ack_current_source_tickets_next222' => true,
        'current_source_cursor_source_close' => 'app.returning.current.cursor.242',
        'current_source_close_token_source_close' => 'app.current.source.close.242',
        'current_view_cookie_source_close' => 'main@view-cookie-242-current',
        'current_trigger_cookie_source_close' => 'main@trigger-cookie-242-current',
        'auto_ack_current_source_closures_source_close' => true,
        'current_source_upsert_target_next239' => 'key_name',
        'current_source_upsert_policy_next239' => 'do-update-returning',
        'current_source_upsert_cursor_next239' => 'app.returning.upsert.cursor.242',
        'current_source_upsert_generation_next239' => 'app.current.source.upsert.generation.242',
        'auto_ack_current_source_upsert_targets_next239' => true,
        'current_source_statement_epoch_next242' => 'app.current.source.statement.epoch.242',
        'current_source_view_program_next242' => 'main@view-cookie-242-current',
        'current_source_trigger_program_next242' => 'main@trigger-cookie-242-current',
        'current_source_schema_cookie_next242' => 'main.schema.cookie.242',
        'current_source_upsert_sql_hash_next242' => 'insert-into-recursive-view-upsert-242',
        'auto_ack_current_source_statement_epochs_next242' => true,
    ],
);

if (
    $summary['status_next242'] !== 'trigger-recursive-view-upsert-current-source-next242-statement-epoch-released'
    || $summary['current_source_statement_epoch_plan_next242']['decision'] !== 'publish-next-source-after-current-upsert-statement-epoch'
    || array_column($summary['visible_returning_payloads_next242'], 'name') !== ['app_summary_child', 'template_child', 'landing_url', 'next_module']
    || $summary['held_next_source_rows_next242'] !== []
) {
    fwrite(STDERR, "application-trigger-recursive-view-upsert-current-source-next242 self-test failed\n");
    exit(1);
}

echo "application-trigger-recursive-view-upsert-current-source-next242 self-test passed\n";
