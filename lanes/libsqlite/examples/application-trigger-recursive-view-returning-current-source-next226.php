<?php

declare(strict_types=1);

$planFiles = glob(dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext*Plan.php') ?: [];
sort($planFiles, SORT_NATURAL);
foreach ($planFiles as $planFile) {
    require_once $planFile;
}

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$currentView = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-226-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-226-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-226',
];
$nextView = $currentView;
$nextView['source'] = 'main@view-cookie-226-next';
$nextView['trigger_source'] = 'main@trigger-cookie-226-next';
$postResetView = $currentView;
$postResetView['source'] = 'main@view-cookie-226-post-reset';
$postResetView['trigger_source'] = 'main@trigger-cookie-226-post-reset';
$followingView = $currentView;
$followingView['source'] = 'main@view-cookie-226-following';
$followingView['trigger_source'] = 'main@trigger-cookie-226-following';
$subsequentNextView = $nextView;
$subsequentNextView['source'] = 'main@view-cookie-226-subsequent-next';
$subsequentNextView['trigger_source'] = 'main@trigger-cookie-226-subsequent-next';

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeFollowingCurrentSeal(
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
    $currentView,
    $nextView,
    [
        ['expr' => 'new.option_name', 'as' => 'name'],
        ['expr' => 'new.option_value', 'as' => 'value'],
        ['expr' => 'event', 'as' => 'event_name'],
        ['expr' => 'ordinal', 'as' => 'ordinal_value'],
        ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
        ['expr' => 'spawn_child', 'as' => 'spawn_child'],
    ],
    [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_226',
        'cursor_name' => 'wp_recursive_view_returning_cursor_226',
        'admit_next_source' => true,
        'rollback_token' => 'wp.rollback.current.226',
        'reset_generation' => 'wp-current-reset-226',
        'post_reset_current_source_token' => 'wp.current.source.postreset.226',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.226',
        'post_reset_view' => $postResetView,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.226',
        'next_cursor' => 'wp.returning.next.cursor.226',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.226',
        'following_current_source_token' => 'wp.current.source.following.base.226',
        'following_cursor' => 'wp.returning.following.cursor.226',
        'following_current_view' => $followingView,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'wp-following-current-226',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.226',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.226',
        'recursive_child_generation' => 'wp-recursive-child-current-226',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.226',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.226',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.226',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.226',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'wp.current.source.drain.226',
        'current_view_cookie_next209' => 'main@view-cookie-226-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-226-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'current_source_yield_token_next212' => 'wp.current.source.yield.226',
        'current_view_yield_cursor_next212' => 'wp.returning.view.yield.cursor.226',
        'current_trigger_yield_cursor_next212' => 'wp.returning.trigger.yield.cursor.226',
        'auto_ack_current_source_yields_next212' => true,
        'current_source_provenance_token_next217' => 'wp.current.source.provenance.226',
        'auto_ack_current_source_provenance_next217' => true,
        'next_source_reset_token_next219' => 'wp.next.source.reset.226',
        'next_source_reset_cursor_next219' => 'wp.returning.next.reset.cursor.226',
        'following_current_source_token_next219' => 'wp.current.source.following.226',
        'auto_ack_next_source_reset_next219' => true,
        'auto_ack_following_current_seal_next226' => true,
        'following_current_view_next219' => $followingView,
        'following_current_input_next219' => [
            ['import_id' => 50, 'name' => 'active_plugins', 'value' => 'plugin-a/plugin.php', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 51, 'name' => 'rewrite_rules', 'value' => 'post-name-rules', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 52, 'name' => 'theme_mods_twentytwentyfive', 'value' => 'serialized-theme-mods', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'subsequent_next_view_next226' => $subsequentNextView,
        'subsequent_next_input_next226' => [
            ['import_id' => 60, 'name' => 'cron', 'value' => 'sealed-next-cron', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 61, 'name' => 'widget_block', 'value' => 'sealed-widget-state', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
    ],
);

if (
    $summary['status_next226'] !== 'trigger-recursive-view-returning-current-source-next226-subsequent-next-visible'
    || $summary['following_current_seal_complete_next226'] !== true
    || $summary['subsequent_next_source_visible_next226'] !== true
    || $summary['subsequent_next_row_count_next226'] !== 2
    || array_column($summary['subsequent_next_payloads_next226'], 'name') !== ['cron', 'widget_block']
    || $summary['blocked_reasons_next226'] !== []
) {
    fwrite(STDERR, "application-trigger-recursive-view-returning-current-source-next226 self-test failed\n");
    exit(1);
}

echo "application-trigger-recursive-view-returning-current-source-next226 self-test passed\n";
