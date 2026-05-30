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
    'source' => 'main@view-cookie-228-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-228-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-228',
];
$nextView = $view;
$nextView['source'] = 'main@view-cookie-228-next';
$nextView['trigger_source'] = 'main@trigger-cookie-228-next';
$postResetView = $view;
$postResetView['source'] = 'main@view-cookie-228-post-reset';
$postResetView['trigger_source'] = 'main@trigger-cookie-228-post-reset';
$followingView = $view;
$followingView['source'] = 'main@view-cookie-228-following';
$followingView['trigger_source'] = 'main@trigger-cookie-228-following';

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::currentReturningSnapshotAcknowledgement(
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
        'savepoint' => 'wp_recursive_view_228',
        'cursor_name' => 'wp_recursive_view_returning_cursor_228',
        'admit_next_source' => true,
        'rollback_token' => 'wp.rollback.current.228',
        'post_reset_view' => $postResetView,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.228',
        'next_cursor' => 'wp.returning.next.cursor.228',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.228',
        'following_current_source_token' => 'wp.current.source.following.228',
        'following_cursor' => 'wp.returning.following.cursor.228',
        'following_current_view' => $followingView,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.228',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.228',
        'recursive_child_generation' => 'wp-recursive-child-current-228',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.228',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.228',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.228',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.228',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'wp.current.source.drain.228',
        'current_view_cookie_next209' => 'main@view-cookie-228-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-228-current',
        'auto_ack_current_source_watermarks_next209' => true,
        'auto_ack_current_source_yields_next212' => true,
        'current_source_epoch_next218' => 'wp.current.source.epoch.228',
        'current_view_epoch_next218' => 'wp.returning.view.epoch.cursor.228',
        'current_trigger_epoch_next218' => 'wp.returning.trigger.epoch.cursor.228',
        'auto_ack_current_source_epochs_next218' => true,
        'current_returning_snapshot_token_snapshot_ack' => 'wp.current.returning.source.228',
        'current_returning_view_source_snapshot_ack' => 'main@view-cookie-228-current',
        'current_returning_trigger_source_snapshot_ack' => 'main@trigger-cookie-228-current',
        'current_returning_source_token_source_seal' => 'wp.current.returning.source.228',
        'current_returning_view_source_source_seal' => 'main@view-cookie-228-current',
        'current_returning_trigger_source_source_seal' => 'main@trigger-cookie-228-current',
        'auto_ack_current_returning_source_seals_source_seal' => true,
        'auto_ack_current_returning_snapshot_acks_snapshot_ack' => true,
    ],
);

if (
    $summary['status_snapshot_ack'] !== 'trigger-recursive-view-returning-current-source-snapshot-ack-source-released'
    || $summary['current_returning_snapshot_plan_snapshot_ack']['decision'] !== 'publish-next-source-after-current-returning-source-ack'
    || array_column($summary['visible_returning_payloads_snapshot_ack'], 'name') !== ['blogdescription_child', 'template_child', 'home', 'next_plugin']
    || $summary['held_next_source_rows_snapshot_ack'] !== []
) {
    fwrite(STDERR, "application-trigger-recursive-view-returning-snapshot-ack self-test failed\n");
    exit(1);
}

echo "application-trigger-recursive-view-returning-snapshot-ack self-test passed\n";
