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
    'source' => 'main@view-cookie-217-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-217-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-217',
];
$nextView = $currentView;
$nextView['source'] = 'main@view-cookie-217-next';
$nextView['trigger_source'] = 'main@trigger-cookie-217-next';

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentSourceProvenanceFence(
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
        'savepoint' => 'wp_recursive_view_217',
        'cursor_name' => 'wp_recursive_view_returning_cursor_217',
        'admit_next_source' => true,
        'rollback_token' => 'wp.rollback.current.217',
        'reset_generation' => 'wp-current-reset-217',
        'post_reset_current_source_token' => 'wp.current.source.postreset.217',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.217',
        'post_reset_view' => array_replace($currentView, ['source' => 'main@view-cookie-217-post-reset', 'trigger_source' => 'main@trigger-cookie-217-post-reset']),
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.217',
        'next_cursor' => 'wp.returning.next.cursor.217',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.217',
        'auto_ack_current_generation_receipts_next203' => true,
        'auto_ack_current_source_watermarks_next209' => true,
        'auto_ack_current_source_yields_next212' => true,
        'auto_ack_current_source_provenance_next217' => true,
        'following_current_source_token' => 'wp.current.source.following.217',
        'following_cursor' => 'wp.returning.following.cursor.217',
        'following_current_view' => array_replace($currentView, ['source' => 'main@view-cookie-217-following', 'trigger_source' => 'main@trigger-cookie-217-following']),
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'wp-following-current-217',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.217',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.217',
        'recursive_child_generation' => 'wp-recursive-child-current-217',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.217',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.217',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.217',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.217',
        'current_source_drain_token_next209' => 'wp.current.source.drain.217',
        'current_view_cookie_next209' => 'main@view-cookie-217-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-217-current',
        'current_source_yield_token_next212' => 'wp.current.source.yield.217',
        'current_view_yield_cursor_next212' => 'wp.returning.view.yield.cursor.217',
        'current_trigger_yield_cursor_next212' => 'wp.returning.trigger.yield.cursor.217',
        'current_source_provenance_token_next217' => 'wp.current.source.provenance.217',
    ],
);

if (
    $summary['status_next217'] !== 'trigger-recursive-view-returning-current-source-next217-provenance-released'
    || $summary['next_source_visible_after_current_source_provenance_next217'] !== true
    || $summary['current_source_provenance_plan_next217']['decision'] !== 'publish-next-source-after-current-returning-provenance'
    || count($summary['visible_returning_rows_next217']) !== 4
    || $summary['held_next_source_rows_next217'] !== []
) {
    fwrite(STDERR, "wordpress-trigger-recursive-view-returning-current-source-next217 self-test failed\n");
    exit(1);
}

echo "wordpress-trigger-recursive-view-returning-current-source-next217 self-test passed\n";
