<?php

declare(strict_types=1);

$planFiles = glob(dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext*Plan.php') ?: [];
sort($planFiles);
foreach ($planFiles as $planFile) {
    require_once $planFile;
}
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$view = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-234-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-234-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-upsert-trigger-234',
];
$nextView = array_replace($view, ['source' => 'main@view-cookie-234-next', 'trigger_source' => 'main@trigger-cookie-234-next']);
$postResetView = array_replace($view, ['source' => 'main@view-cookie-234-post-reset', 'trigger_source' => 'main@trigger-cookie-234-post-reset']);
$followingView = array_replace($view, ['source' => 'main@view-cookie-234-following', 'trigger_source' => 'main@trigger-cookie-234-following']);

$summary = SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentSourceUpsertReceipt(
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
        'savepoint' => 'wp_recursive_view_234',
        'cursor_name' => 'wp_recursive_view_returning_cursor_234',
        'admit_next_source' => true,
        'rollback_token' => 'wp.rollback.current.234',
        'post_reset_view' => $postResetView,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.234',
        'next_cursor' => 'wp.returning.next.cursor.234',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.234',
        'following_current_source_token' => 'wp.current.source.following.234',
        'following_cursor' => 'wp.returning.following.cursor.234',
        'following_current_view' => $followingView,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.234',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.234',
        'recursive_child_generation' => 'wp-recursive-child-current-234',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.234',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.234',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.234',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.234',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'wp.current.source.drain.234',
        'current_view_cookie_next209' => 'main@view-cookie-234-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-234-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'current_source_yield_token_next212' => 'wp.current.source.yield.234',
        'current_view_yield_cursor_next212' => 'wp.returning.view.yield.cursor.234',
        'current_trigger_yield_cursor_next212' => 'wp.returning.trigger.yield.cursor.234',
        'auto_ack_current_source_yields_next212' => true,
        'current_source_epoch_next218' => 'wp.current.source.epoch.234',
        'current_view_epoch_next218' => 'wp.returning.view.epoch.cursor.234',
        'current_trigger_epoch_next218' => 'wp.returning.trigger.epoch.cursor.234',
        'auto_ack_current_source_epochs_next218' => true,
        'current_source_ticket_next222' => 'wp.current.source.ticket.234',
        'current_view_source_next222' => 'main@view-cookie-234-current',
        'current_trigger_source_next222' => 'main@trigger-cookie-234-current',
        'auto_ack_current_source_tickets_next222' => true,
        'current_source_cursor_source_close' => 'wp.returning.current.cursor.234',
        'current_source_close_token_source_close' => 'wp.current.source.close.234',
        'current_view_cookie_source_close' => 'main@view-cookie-234-current',
        'current_trigger_cookie_source_close' => 'main@trigger-cookie-234-current',
        'auto_ack_current_source_closures_source_close' => true,
        'current_source_upsert_token_next234' => 'wp.current.source.upsert.234',
        'current_upsert_view_cookie_next234' => 'main@view-cookie-234-current',
        'current_upsert_trigger_cookie_next234' => 'main@trigger-cookie-234-current',
        'auto_ack_current_source_upserts_next234' => true,
    ],
);

if (
    $summary['status_next234'] !== 'trigger-recursive-view-upsert-current-source-next234-upsert-released'
    || $summary['current_source_upsert_plan_next234']['decision'] !== 'publish-next-source-after-current-recursive-view-upsert'
    || array_column($summary['visible_returning_payloads_next234'], 'name') !== ['blogdescription_child', 'template_child', 'home', 'next_plugin']
    || $summary['held_next_source_rows_next234'] !== []
) {
    fwrite(STDERR, "application-trigger-recursive-view-upsert-current-source-next234 self-test failed\n");
    exit(1);
}

echo "application-trigger-recursive-view-upsert-current-source-next234 self-test passed\n";
echo json_encode([
    'scenario' => 'application-trigger-recursive-view-upsert-current-source-next234',
    'status' => $summary['status_next234'],
    'visibleNames' => array_column($summary['visible_returning_payloads_next234'], 'name'),
    'receiptCount' => count($summary['required_current_source_upsert_receipts_next234']),
    'applicationUse' => 'Copied wp_options imports through recursive INSTEAD OF view UPSERT triggers hold next-source RETURNING rows until the current-source conflict-key receipts are acknowledged.',
    'dependencyClosure' => $summary['dependency_closure_next234'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
