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

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$view = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-203-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-203-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-203',
];
$nextView = $view;
$nextView['source'] = 'main@view-cookie-203-next';
$nextView['trigger_source'] = 'main@trigger-cookie-203-next';
$postResetView = $view;
$postResetView['source'] = 'main@view-cookie-203-post-reset';
$postResetView['trigger_source'] = 'main@trigger-cookie-203-post-reset';
$followingView = $view;
$followingView['source'] = 'main@view-cookie-203-following';
$followingView['trigger_source'] = 'main@trigger-cookie-203-following';

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentGenerationReceipt(
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
        'savepoint' => 'app_recursive_view_203',
        'cursor_name' => 'app_recursive_view_returning_cursor_203',
        'admit_next_source' => true,
        'rollback_token' => 'app.rollback.current.203',
        'reset_generation' => 'app-current-reset-203',
        'post_reset_current_source_token' => 'app.current.source.postreset.203',
        'post_reset_cursor' => 'app.returning.postreset.cursor.203',
        'post_reset_view' => $postResetView,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'base_url', 'value' => 'https://fresh.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'routing_rules', 'value' => 'fresh-rules', 'load_policy_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'app.next.source.203',
        'next_cursor' => 'app.returning.next.cursor.203',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'app.returning.next.cursor.203',
        'following_current_source_token' => 'app.current.source.following.203',
        'following_cursor' => 'app.returning.following.cursor.203',
        'following_current_view' => $followingView,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'app_summary', 'value' => 'after-next', 'load_policy_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'theme_style_key', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => true],
        ],
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'auto_ack_current_generation_receipts_next203' => true,
    ],
);

if (
    $summary['status_next203'] !== 'trigger-recursive-view-returning-current-source-next203-generation-released'
    || $summary['current_generation_plan_next203']['decision'] !== 'publish-next-source-after-current-generation-handoff'
    || array_column($summary['visible_returning_payloads_next203'], 'name') !== ['app_summary_child', 'template_child', 'landing_url', 'next_module']
    || $summary['held_next_source_rows_next203'] !== []
) {
    fwrite(STDERR, "application-trigger-recursive-view-returning-current-source-next203 self-test failed\n");
    exit(1);
}

echo "application-trigger-recursive-view-returning-current-source-next203 self-test passed\n";
