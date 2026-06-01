<?php

declare(strict_types=1);

$planFiles = glob(dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext*Plan.php') ?: [];
sort($planFiles, SORT_NATURAL);
foreach ($planFiles as $planFile) {
    require_once $planFile;
}

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$currentView = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-230-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-230-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-230',
];
$nextView = $currentView;
$nextView['source'] = 'main@view-cookie-230-next';
$nextView['trigger_source'] = 'main@trigger-cookie-230-next';
$postResetView = $currentView;
$postResetView['source'] = 'main@view-cookie-230-post-reset';
$postResetView['trigger_source'] = 'main@trigger-cookie-230-post-reset';
$followingView = $currentView;
$followingView['source'] = 'main@view-cookie-230-following';
$followingView['trigger_source'] = 'main@trigger-cookie-230-following';
$subsequentNextView = $nextView;
$subsequentNextView['source'] = 'main@view-cookie-230-subsequent-next';
$subsequentNextView['trigger_source'] = 'main@trigger-cookie-230-subsequent-next';

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentSourceEpochReceipt(
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
        ['expr' => 'ordinal', 'as' => 'ordinal_value'],
        ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
        ['expr' => 'spawn_child', 'as' => 'spawn_child'],
    ],
    [
        'key' => 'key_name',
        'savepoint' => 'app_recursive_view_230',
        'cursor_name' => 'app_recursive_view_returning_cursor_230',
        'admit_next_source' => true,
        'rollback_token' => 'app.rollback.current.230',
        'reset_generation' => 'app-current-reset-230',
        'post_reset_current_source_token' => 'app.current.source.postreset.230',
        'post_reset_cursor' => 'app.returning.postreset.cursor.230',
        'post_reset_view' => $postResetView,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'base_url', 'value' => 'https://fresh.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'routing_rules', 'value' => 'fresh-rules', 'load_policy_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'app.next.source.230',
        'next_cursor' => 'app.returning.next.cursor.230',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'app.returning.next.cursor.230',
        'following_current_source_token' => 'app.current.source.following.base.230',
        'following_cursor' => 'app.returning.following.cursor.230',
        'following_current_view' => $followingView,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'app_summary', 'value' => 'after-next', 'load_policy_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'theme_style_key', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'app-following-current-230',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'app.current.source.recursive.child.230',
        'recursive_child_cursor' => 'app.returning.recursive.child.cursor.230',
        'recursive_child_generation' => 'app-recursive-child-current-230',
        'current_generation_next203' => 'app.current.recursive.returning.generation.230',
        'expected_current_generation_next203' => 'app.current.recursive.returning.generation.230',
        'current_handoff_cursor_next203' => 'app.returning.current.handoff.cursor.230',
        'current_generation_commit_marker_next203' => 'app.current.recursive.returning.commit.230',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'app.current.source.drain.230',
        'current_view_cookie_next209' => 'main@view-cookie-230-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-230-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'current_source_yield_token_next212' => 'app.current.source.yield.230',
        'current_view_yield_cursor_next212' => 'app.returning.view.yield.cursor.230',
        'current_trigger_yield_cursor_next212' => 'app.returning.trigger.yield.cursor.230',
        'auto_ack_current_source_yields_next212' => true,
        'current_source_provenance_token_next217' => 'app.current.source.provenance.230',
        'auto_ack_current_source_provenance_next217' => true,
        'next_source_reset_token_next219' => 'app.next.source.reset.230',
        'next_source_reset_cursor_next219' => 'app.returning.next.reset.cursor.230',
        'following_current_source_token_next219' => 'app.current.source.following.230',
        'following_current_view_next219' => $followingView,
        'following_current_input_next219' => [
            ['import_id' => 50, 'name' => 'active_modules', 'value' => 'module-a/module.php', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 51, 'name' => 'routing_rules', 'value' => 'post-name-rules', 'load_policy_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 52, 'name' => 'theme_mods_modern_theme', 'value' => 'serialized-theme-mods', 'load_policy_flag' => 'no', 'spawn_child' => false],
        ],
        'auto_ack_next_source_reset_next219' => true,
        'following_current_seal_token_next226' => 'app.following.current.seal.230',
        'following_current_seal_cursor_next226' => 'app.returning.following.current.cursor.230',
        'auto_ack_following_current_seal_next226' => true,
        'subsequent_next_source_token_next226' => 'app.subsequent.next.source.230',
        'subsequent_next_view_next226' => $subsequentNextView,
        'subsequent_next_input_next226' => [
            ['import_id' => 60, 'name' => 'cron', 'value' => 'sealed-next-cron', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 61, 'name' => 'widget_block', 'value' => 'sealed-widget-state', 'load_policy_flag' => 'yes', 'spawn_child' => true],
        ],
        'auto_ack_current_source_epoch' => true,
        'current_source_epoch' => 'app.current.source.epoch.230',
        'current_source_epoch_cursor' => 'app.returning.current.epoch.cursor.230',
    ],
);

if (
    $summary['status_current_source_epoch'] !== 'trigger-recursive-view-returning-current-source-epoch-subsequent-next-visible'
    || $summary['current_source_epoch_complete'] !== true
    || $summary['subsequent_next_source_visible_after_epoch'] !== true
    || $summary['subsequent_next_row_count'] !== 2
    || array_slice(array_column($summary['visible_returning_payloads'], 'name'), -2) !== ['cron', 'widget_block']
    || $summary['blocked_reasons'] !== []
) {
    fwrite(STDERR, "application-trigger-recursive-view-returning-current-source-epoch self-test failed\n");
    exit(1);
}

echo "application-trigger-recursive-view-returning-current-source-epoch self-test passed\n";
