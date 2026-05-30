<?php

declare(strict_types=1);

$returningPlanFiles = glob(dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext*Plan.php') ?: [];
usort($returningPlanFiles, 'strnatcmp');
foreach ($returningPlanFiles as $planFile) {
    require_once $planFile;
}
$upsertPlanFiles = glob(dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext*Plan.php') ?: [];
usort($upsertPlanFiles, 'strnatcmp');
foreach ($upsertPlanFiles as $planFile) {
    require_once $planFile;
}

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

$view = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-254-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-254-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-upsert-mapping-254',
];
$nextView = $view;
$nextView['source'] = 'main@view-cookie-254-next';
$nextView['trigger_source'] = 'main@trigger-cookie-254-next';
$postResetView = $view;
$postResetView['source'] = 'main@view-cookie-254-post-reset';
$postResetView['trigger_source'] = 'main@trigger-cookie-254-post-reset';
$followingView = $view;
$followingView['source'] = 'main@view-cookie-254-following';
$followingView['trigger_source'] = 'main@trigger-cookie-254-following';

$summary = SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::executeCurrentViewMappingReceipt(
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
        'savepoint' => 'wp_recursive_view_254',
        'cursor_name' => 'wp_recursive_view_returning_cursor_254',
        'admit_next_source' => true,
        'rollback_token' => 'wp.rollback.current.254',
        'reset_generation' => 'wp-current-reset-254',
        'post_reset_current_source_token' => 'wp.current.source.postreset.254',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.254',
        'post_reset_view' => $postResetView,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.254',
        'next_cursor' => 'wp.returning.next.cursor.254',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.254',
        'following_current_source_token' => 'wp.current.source.following.254',
        'following_cursor' => 'wp.returning.following.cursor.254',
        'following_current_view' => $followingView,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'wp-following-current-254',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.254',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.254',
        'recursive_child_generation' => 'wp-recursive-child-current-254',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.254',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.254',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.254',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.254',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'wp.current.source.drain.254',
        'current_view_cookie_next209' => 'main@view-cookie-254-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-254-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'current_source_yield_token_next212' => 'wp.current.source.yield.254',
        'current_view_yield_cursor_next212' => 'wp.returning.view.yield.cursor.254',
        'current_trigger_yield_cursor_next212' => 'wp.returning.trigger.yield.cursor.254',
        'auto_ack_current_source_yields_next212' => true,
        'current_source_epoch_next218' => 'wp.current.source.epoch.254',
        'current_view_epoch_next218' => 'wp.returning.view.epoch.cursor.254',
        'current_trigger_epoch_next218' => 'wp.returning.trigger.epoch.cursor.254',
        'auto_ack_current_source_epochs_next218' => true,
        'current_source_ticket_next222' => 'wp.current.source.ticket.254',
        'current_view_source_next222' => 'main@view-cookie-254-current',
        'current_trigger_source_next222' => 'main@trigger-cookie-254-current',
        'auto_ack_current_source_tickets_next222' => true,
        'current_source_cursor_source_close' => 'wp.returning.current.cursor.254',
        'current_source_close_token_source_close' => 'wp.current.source.close.254',
        'current_view_cookie_source_close' => 'main@view-cookie-254-current',
        'current_trigger_cookie_source_close' => 'main@trigger-cookie-254-current',
        'auto_ack_current_source_closures_source_close' => true,
        'current_source_upsert_token_next234' => 'wp.current.source.upsert.254',
        'current_upsert_view_cookie_next234' => 'main@view-cookie-254-current',
        'current_upsert_trigger_cookie_next234' => 'main@trigger-cookie-254-current',
        'auto_ack_current_source_upserts_next234' => true,
        'current_source_upsert_action_token_next237' => 'wp.current.source.upsert.action.254',
        'current_upsert_action_view_cookie_next237' => 'main@view-cookie-254-current',
        'current_upsert_action_trigger_cookie_next237' => 'main@trigger-cookie-254-current',
        'auto_ack_current_source_upsert_actions_next237' => true,
        'current_source_upsert_close_token_next241' => 'wp.current.source.upsert.close.254',
        'current_source_upsert_generation_next241' => 'main@source-generation-254-current',
        'current_upsert_close_view_cookie_next241' => 'main@view-cookie-254-current',
        'current_upsert_close_trigger_cookie_next241' => 'main@trigger-cookie-254-current',
        'auto_ack_current_source_upsert_closes_next241' => true,
        'current_source_upsert_statement_id_next244' => 'wp.current.source.upsert.statement.254',
        'current_source_upsert_commit_watermark_next244' => 'wp.current.source.upsert.commit.254',
        'current_upsert_commit_view_cookie_next244' => 'main@view-cookie-254-current',
        'current_upsert_commit_trigger_cookie_next244' => 'main@trigger-cookie-254-current',
        'auto_ack_current_source_upsert_commits_next244' => true,
        'current_source_statement_sequence_next247' => 254,
        'next_source_statement_sequence_next247' => 255,
        'current_source_sequence_view_cookie_next247' => 'main@view-cookie-254-current',
        'current_source_sequence_trigger_cookie_next247' => 'main@trigger-cookie-254-current',
        'current_source_sequence_cursor_next247' => 'wp.returning.current.sequence.cursor.254',
        'auto_ack_current_source_statement_sequences_next247' => true,
        'current_source_rowid_provenance_token_next250' => 'wp.current.source.rowid.provenance.254',
        'auto_ack_current_source_rowid_provenance_next250' => true,
        'current_view_mapping_source_token_next254' => 'main@view-cookie-254-current',
        'current_view_mapping_trigger_token_next254' => 'main@trigger-cookie-254-current',
        'auto_ack_current_view_mapping_receipts_next254' => true,
    ],
);

if (
    $summary['status_next254'] !== 'trigger-recursive-view-upsert-current-source-next254-view-mapping-released'
    || $summary['current_view_mapping_plan_next254']['decision'] !== 'publish-next-source-after-current-recursive-view-upsert-mapping'
    || array_column($summary['visible_returning_payloads_next254'], 'name') !== ['blogdescription_child', 'template_child', 'home', 'next_plugin']
    || $summary['held_next_source_rows_next254'] !== []
    || count($summary['required_current_view_mapping_receipts_next254']) !== 2
    || $summary['current_view_mapping_rows_next254'][0]['mapping']['name']['target'] !== 'option_name'
) {
    fwrite(STDERR, "application-trigger-recursive-view-upsert-current-source-next254 self-test failed\n");
    exit(1);
}

echo "application-trigger-recursive-view-upsert-current-source-next254 self-test passed\n";
