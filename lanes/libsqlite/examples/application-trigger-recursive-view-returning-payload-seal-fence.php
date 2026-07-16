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

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$view = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-213-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-213-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-213',
];
$nextView = $view;
$nextView['source'] = 'main@view-cookie-213-next';
$nextView['trigger_source'] = 'main@trigger-cookie-213-next';
$postResetView = $view;
$postResetView['source'] = 'main@view-cookie-213-post-reset';
$postResetView['trigger_source'] = 'main@trigger-cookie-213-post-reset';
$followingView = $view;
$followingView['source'] = 'main@view-cookie-213-following';
$followingView['trigger_source'] = 'main@trigger-cookie-213-following';

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::currentSourcePayloadSealFence(
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
        'savepoint' => 'app_recursive_view_213',
        'cursor_name' => 'app_recursive_view_returning_cursor_213',
        'admit_next_source' => true,
        'rollback_token' => 'app.rollback.current.213',
        'post_reset_view' => $postResetView,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'base_url', 'value' => 'https://fresh.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'routing_rules', 'value' => 'fresh-rules', 'load_policy_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'app.next.source.213',
        'next_cursor' => 'app.returning.next.cursor.213',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'app.returning.next.cursor.213',
        'following_current_source_token' => 'app.current.source.following.213',
        'following_cursor' => 'app.returning.following.cursor.213',
        'following_current_view' => $followingView,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'app_summary', 'value' => 'after-next', 'load_policy_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'theme_style_key', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => true],
        ],
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'app.current.source.recursive.child.213',
        'recursive_child_cursor' => 'app.returning.recursive.child.cursor.213',
        'recursive_child_generation' => 'app-recursive-child-current-213',
        'current_generation_next203' => 'app.current.recursive.returning.generation.213',
        'expected_current_generation_next203' => 'app.current.recursive.returning.generation.213',
        'current_handoff_cursor_next203' => 'app.returning.current.handoff.cursor.213',
        'current_generation_commit_marker_next203' => 'app.current.recursive.returning.commit.213',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'app.current.source.drain.213',
        'current_view_cookie_next209' => 'main@view-cookie-213-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-213-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'current_source_yield_token_next212' => 'app.current.source.yield.213',
        'auto_ack_current_source_yields_next212' => true,
        'current_source_payload_seal_token_next213' => 'app.current.source.payload.seal.213',
        'auto_ack_current_source_payload_seals_next213' => true,
    ],
);

if (
    $summary['status_next213'] !== 'trigger-recursive-view-returning-current-source-next213-payload-seal-released'
    || $summary['current_source_payload_seal_plan_next213']['decision'] !== 'publish-next-source-after-current-payload-seal'
    || array_column($summary['visible_returning_payloads_next213'], 'name') !== ['app_summary_child', 'template_child', 'landing_url', 'next_module']
    || $summary['held_next_source_rows_next213'] !== []
) {
    fwrite(STDERR, "application-trigger-recursive-view-returning-payload-seal-fence self-test failed\n");
    exit(1);
}

echo "application-trigger-recursive-view-returning-payload-seal-fence self-test passed\n";
