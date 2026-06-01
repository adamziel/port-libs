<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$view = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-source-sequence-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-source-sequence-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-source-sequence',
];
$nextView = $view;
$nextView['source'] = 'main@view-cookie-source-sequence-next';
$nextView['trigger_source'] = 'main@trigger-cookie-source-sequence-next';
$postResetView = $view;
$postResetView['source'] = 'main@view-cookie-source-sequence-post-reset';
$postResetView['trigger_source'] = 'main@trigger-cookie-source-sequence-post-reset';
$followingView = $view;
$followingView['source'] = 'main@view-cookie-source-sequence-following';
$followingView['trigger_source'] = 'main@trigger-cookie-source-sequence-following';

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeSourceSequenceFence(
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
        ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
        ['expr' => 'spawn_child', 'as' => 'spawn_child'],
    ],
    [
        'savepoint' => 'app_recursive_view_source_sequence',
        'cursor_name' => 'app_recursive_view_returning_cursor_source_sequence',
        'current_generation' => 'app-current-returning-source-sequence',
        'next_generation' => 'app-next-returning-source-sequence',
        'page_size' => 3,
        'admit_next_source' => true,
        'current_source_token' => 'app.current.source.source_sequence',
        'drain_ack_token' => 'app.returning.drain.source_sequence',
        'rollback_token' => 'app.rollback.current.source_sequence',
        'reset_generation' => 'app-current-reset-source-sequence',
        'post_reset_current_source_token' => 'app.current.source.postreset.source_sequence',
        'post_reset_cursor' => 'app.returning.postreset.cursor.source_sequence',
        'post_reset_view' => $postResetView,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'base_url', 'value' => 'https://fresh.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'routing_rules', 'value' => 'fresh-rules', 'load_policy_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'app.next.source.source_sequence',
        'next_cursor' => 'app.returning.next.cursor.source_sequence',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'app.returning.next.cursor.source_sequence',
        'following_current_source_token' => 'app.current.source.following.source_sequence',
        'following_cursor' => 'app.returning.following.cursor.source_sequence',
        'following_current_view' => $followingView,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'app_summary', 'value' => 'after-next', 'load_policy_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'theme_style_key', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'app-following-current-source-sequence',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'app.current.source.recursive.child.source_sequence',
        'recursive_child_cursor' => 'app.returning.recursive.child.cursor.source_sequence',
        'recursive_child_generation' => 'app-recursive-child-current-source-sequence',
        'current_generation_next203' => 'app.current.recursive.returning.generation.source_sequence',
        'expected_current_generation_next203' => 'app.current.recursive.returning.generation.source_sequence',
        'current_handoff_cursor_next203' => 'app.returning.current.handoff.cursor.source_sequence',
        'current_generation_commit_marker_next203' => 'app.current.recursive.returning.commit.source_sequence',
        'auto_ack_current_generation_receipts_next203' => true,
        'auto_ack_current_source_sequence_fence_source_sequence_fence' => true,
        'current_source_sequence_fence_token_source_sequence_fence' => 'app.current.returning.source.sequence.source_sequence',
        'next_source_sequence_fence_token_source_sequence_fence' => 'app.next.returning.source.sequence.source_sequence',
    ],
);

if (
    $summary['status_source_sequence_fence'] !== 'trigger-recursive-view-returning-current-source-source-sequence-source-sequence-released'
    || $summary['source_sequence_plan_source_sequence_fence']['decision'] !== 'publish-next-source-after-current-source-sequence'
    || $summary['next_source_visible_after_source_sequence_fence_source_sequence_fence'] !== true
    || array_column($summary['visible_returning_payloads_source_sequence_fence'], 'name') !== ['app_summary_child', 'template_child', 'landing_url', 'next_module']
    || $summary['held_next_source_rows_source_sequence_fence'] !== []
) {
    fwrite(STDERR, "application-trigger-recursive-view-returning-current-source-source-sequence self-test failed\n");
    exit(1);
}

echo "application-trigger-recursive-view-returning-current-source-source-sequence self-test passed\n";
