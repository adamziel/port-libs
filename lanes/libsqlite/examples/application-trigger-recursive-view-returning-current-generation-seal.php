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
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$view = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-229-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-229-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-229',
];
$nextView = $view;
$nextView['source'] = 'main@view-cookie-229-next';
$nextView['trigger_source'] = 'main@trigger-cookie-229-next';
$postResetView = $view;
$postResetView['source'] = 'main@view-cookie-229-post-reset';
$postResetView['trigger_source'] = 'main@trigger-cookie-229-post-reset';
$followingView = $view;
$followingView['source'] = 'main@view-cookie-229-following';
$followingView['trigger_source'] = 'main@trigger-cookie-229-following';

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentReturningGenerationSeal(
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
        ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
        ['expr' => 'spawn_child', 'as' => 'spawn_child'],
    ],
    [
        'savepoint' => 'wp_recursive_view_229',
        'cursor_name' => 'wp_recursive_view_returning_cursor_229',
        'admit_next_source' => true,
        'rollback_token' => 'wp.rollback.current.229',
        'post_reset_view' => $postResetView,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.229',
        'next_cursor' => 'wp.returning.next.cursor.229',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.229',
        'following_current_source_token' => 'wp.current.source.following.229',
        'following_cursor' => 'wp.returning.following.cursor.229',
        'following_current_view' => $followingView,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.229',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.229',
        'recursive_child_generation' => 'wp-recursive-child-current-229',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.229',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.229',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.229',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.229',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'wp.current.source.drain.229',
        'current_view_cookie_next209' => 'main@view-cookie-229-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-229-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'auto_ack_current_source_yields_next212' => true,
        'current_source_epoch_next218' => 'wp.current.source.epoch.229',
        'current_view_epoch_next218' => 'wp.returning.view.epoch.cursor.229',
        'current_trigger_epoch_next218' => 'wp.returning.trigger.epoch.cursor.229',
        'auto_ack_current_source_epochs_next218' => true,
        'auto_ack_current_returning_source_seals_source_seal' => true,
        'current_returning_source_generation' => 'wp.current.returning.source.generation.229',
        'current_returning_view_generation' => 'main@view-cookie-229-current',
        'current_returning_trigger_generation' => 'main@trigger-cookie-229-current',
        'auto_ack_current_returning_generation_seals' => true,
    ],
);

if (
    $summary['status_generation_seal'] !== 'trigger-recursive-view-returning-current-source-generation-released'
    || $summary['current_returning_source_plan_generation_seal']['decision'] !== 'publish-next-source-after-current-returning-generation-seal'
    || array_column($summary['visible_returning_payloads_generation_seal'], 'name') !== ['blogdescription_child', 'template_child', 'home', 'next_plugin']
    || $summary['held_next_source_rows_generation_seal'] !== []
) {
    fwrite(STDERR, "application-trigger-recursive-view-returning-current-generation-seal self-test failed\n");
    exit(1);
}

echo "application-trigger-recursive-view-returning-current-generation-seal self-test passed\n";
