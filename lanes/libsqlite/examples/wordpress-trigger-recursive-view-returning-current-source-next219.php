<?php

declare(strict_types=1);

$planFiles = glob(dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext*Plan.php') ?: [];
sort($planFiles);
foreach ($planFiles as $planFile) {
    require_once $planFile;
}

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$currentView = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-219-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-219-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-219',
];
$nextView = $currentView;
$nextView['source'] = 'main@view-cookie-219-next';
$nextView['trigger_source'] = 'main@trigger-cookie-219-next';
$followingView = $currentView;
$followingView['source'] = 'main@view-cookie-219-following';
$followingView['trigger_source'] = 'main@trigger-cookie-219-following';

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeFollowingCurrentResetFence(
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
        ['expr' => 'depth', 'as' => 'depth_value'],
        ['expr' => 'ordinal', 'as' => 'ordinal_value'],
        ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
        ['expr' => 'spawn_child', 'as' => 'spawn_child'],
    ],
    [
        'savepoint' => 'wp_recursive_view_219',
        'cursor_name' => 'wp_recursive_view_returning_cursor_219',
        'admit_next_source' => true,
        'rollback_token' => 'wp.rollback.current.219',
        'reset_generation' => 'wp-current-reset-219',
        'post_reset_current_source_token' => 'wp.current.source.postreset.219',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.219',
        'post_reset_view' => array_replace($currentView, ['source' => 'main@view-cookie-219-post-reset', 'trigger_source' => 'main@trigger-cookie-219-post-reset']),
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.219',
        'next_cursor' => 'wp.returning.next.cursor.219',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.219',
        'following_current_source_token' => 'wp.current.source.following.base.219',
        'following_cursor' => 'wp.returning.following.cursor.219',
        'following_current_view' => $followingView,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'wp-following-current-219',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.219',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.219',
        'recursive_child_generation' => 'wp-recursive-child-current-219',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.219',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.219',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.219',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.219',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'wp.current.source.drain.219',
        'current_view_cookie_next209' => 'main@view-cookie-219-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-219-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'current_source_yield_token_next212' => 'wp.current.source.yield.219',
        'current_view_yield_cursor_next212' => 'wp.returning.view.yield.cursor.219',
        'current_trigger_yield_cursor_next212' => 'wp.returning.trigger.yield.cursor.219',
        'auto_ack_current_source_yields_next212' => true,
        'current_source_provenance_token_next217' => 'wp.current.source.provenance.219',
        'auto_ack_current_source_provenance_next217' => true,
        'next_source_reset_token_next219' => 'wp.next.source.reset.219',
        'next_source_reset_cursor_next219' => 'wp.returning.next.reset.cursor.219',
        'auto_ack_next_source_reset_next219' => true,
        'following_current_source_token_next219' => 'wp.current.source.following.219',
        'following_current_view_next219' => $followingView,
        'following_current_input_next219' => [
            ['import_id' => 50, 'name' => 'active_plugins', 'value' => 'plugin-a/plugin.php', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 51, 'name' => 'rewrite_rules', 'value' => 'post-name-rules', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 52, 'name' => 'theme_mods_twentytwentyfive', 'value' => 'serialized-theme-mods', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
    ],
);

if (
    $summary['status_next219'] !== 'trigger-recursive-view-returning-current-source-next219-following-current-visible'
    || $summary['following_current_source_visible_next219'] !== true
    || $summary['next_source_reset_plan_next219']['decision'] !== 'admit-following-current-source-after-next-returning-reset'
    || count($summary['following_current_rows_next219']) !== 3
    || array_column($summary['following_current_payloads_next219'], 'name') !== ['active_plugins', 'rewrite_rules', 'theme_mods_twentytwentyfive']
) {
    fwrite(STDERR, "wordpress-trigger-recursive-view-returning-current-source-next219 self-test failed\n");
    exit(1);
}

echo "wordpress-trigger-recursive-view-returning-current-source-next219 self-test passed\n";
