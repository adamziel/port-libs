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

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$currentView = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-208-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-208-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-208',
];
$nextView = $currentView;
$nextView['source'] = 'main@view-cookie-208-next';
$nextView['trigger_source'] = 'main@trigger-cookie-208-next';
$postResetView = $currentView;
$postResetView['source'] = 'main@view-cookie-208-post-reset';
$postResetView['trigger_source'] = 'main@trigger-cookie-208-post-reset';
$followingView = $currentView;
$followingView['source'] = 'main@view-cookie-208-following';
$followingView['trigger_source'] = 'main@trigger-cookie-208-following';

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentCursorCloseFence(
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
    $currentView,
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
        'key' => 'key_name',
        'savepoint' => 'app_recursive_view_208',
        'cursor_name' => 'app_recursive_view_returning_cursor_208',
        'current_generation' => 'app-current-returning-208',
        'next_generation' => 'app-next-returning-208',
        'page_size' => 3,
        'admit_next_source' => true,
        'current_source_token' => 'app.current.source.208',
        'drain_ack_token' => 'app.returning.drain.208',
        'rollback_token' => 'app.rollback.current.208',
        'reset_generation' => 'app-current-reset-208',
        'post_reset_current_source_token' => 'app.current.source.postreset.208',
        'post_reset_cursor' => 'app.returning.postreset.cursor.208',
        'post_reset_view' => $postResetView,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'base_url', 'value' => 'https://fresh.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'routing_rules', 'value' => 'fresh-rules', 'load_policy_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'app.next.source.208',
        'next_cursor' => 'app.returning.next.cursor.208',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'app.returning.next.cursor.208',
        'following_current_source_token' => 'app.current.source.following.208',
        'following_cursor' => 'app.returning.following.cursor.208',
        'following_current_view' => $followingView,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'app_summary', 'value' => 'after-next', 'load_policy_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'theme_style_key', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'app-following-current-208',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'app.current.source.recursive.child.208',
        'recursive_child_cursor' => 'app.returning.recursive.child.cursor.208',
        'recursive_child_generation' => 'app-recursive-child-current-208',
        'current_generation_next203' => 'app.current.recursive.returning.generation.208',
        'expected_current_generation_next203' => 'app.current.recursive.returning.generation.208',
        'current_handoff_cursor_next203' => 'app.returning.current.handoff.cursor.208',
        'current_generation_commit_marker_next203' => 'app.current.recursive.returning.commit.208',
        'auto_ack_current_generation_receipts_next203' => true,
        'yield_current_source_token_next206' => 'app.current.recursive.returning.source.208',
        'yield_current_cursor_next206' => 'app.returning.current.cursor.208',
        'yield_statement_token_next206' => 'app.recursive.view.returning.statement.208',
        'close_current_cursor_next208' => 'app.returning.current.cursor.208',
        'expected_close_current_cursor_next208' => 'app.returning.current.cursor.208',
        'close_statement_token_next208' => 'app.recursive.view.returning.close.208',
    ],
);

if (
    $summary['status_next208'] !== 'trigger-recursive-view-returning-current-source-next208-cursor-closed'
    || $summary['next_source_visible_after_current_cursor_close_next208'] !== true
    || $summary['current_cursor_close_plan_next208']['decision'] !== 'publish-next-source-after-current-returning-cursor-close'
    || count($summary['visible_returning_rows_next208']) !== 4
    || $summary['held_next_source_rows_next208'] !== []
) {
    fwrite(STDERR, "application-trigger-recursive-view-returning-current-source-next208 self-test failed\n");
    exit(1);
}

echo "application-trigger-recursive-view-returning-current-source-next208 self-test passed\n";
