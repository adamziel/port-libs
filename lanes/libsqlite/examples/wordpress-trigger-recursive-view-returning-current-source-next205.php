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
    'source' => 'main@view-cookie-205-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-205-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-205',
];
$nextView = $view;
$nextView['source'] = 'main@view-cookie-205-next';
$nextView['trigger_source'] = 'main@trigger-cookie-205-next';
$postResetView = $view;
$postResetView['source'] = 'main@view-cookie-205-post-reset';
$postResetView['trigger_source'] = 'main@trigger-cookie-205-post-reset';
$followingView = $view;
$followingView['source'] = 'main@view-cookie-205-following';
$followingView['trigger_source'] = 'main@trigger-cookie-205-following';

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext205(
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
        ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
        ['expr' => 'spawn_child', 'as' => 'spawn_child'],
    ],
    [
        'savepoint' => 'wp_recursive_view_205',
        'cursor_name' => 'wp_recursive_view_returning_cursor_205',
        'current_generation' => 'wp-current-returning-205',
        'next_generation' => 'wp-next-returning-205',
        'page_size' => 3,
        'admit_next_source' => true,
        'current_source_token' => 'wp.current.source.205',
        'drain_ack_token' => 'wp.returning.drain.205',
        'rollback_token' => 'wp.rollback.current.205',
        'reset_generation' => 'wp-current-reset-205',
        'post_reset_current_source_token' => 'wp.current.source.postreset.205',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.205',
        'post_reset_view' => $postResetView,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.205',
        'next_cursor' => 'wp.returning.next.cursor.205',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.205',
        'following_current_source_token' => 'wp.current.source.following.205',
        'following_cursor' => 'wp.returning.following.cursor.205',
        'following_current_view' => $followingView,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'wp-following-current-205',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.205',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.205',
        'recursive_child_generation' => 'wp-recursive-child-current-205',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.205',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.205',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.205',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.205',
        'auto_ack_current_generation_receipts_next203' => true,
        'auto_ack_current_source_sequence_next205' => true,
        'current_source_sequence_token_next205' => 'wp.current.returning.source.sequence.205',
        'next_source_sequence_token_next205' => 'wp.next.returning.source.sequence.205',
    ],
);

if (
    $summary['status_next205'] !== 'trigger-recursive-view-returning-current-source-next205-source-sequence-released'
    || $summary['source_sequence_plan_next205']['decision'] !== 'publish-next-source-after-current-source-sequence'
    || $summary['next_source_visible_after_source_sequence_next205'] !== true
    || array_column($summary['visible_returning_payloads_next205'], 'name') !== ['blogdescription_child', 'template_child', 'home', 'next_plugin']
    || $summary['held_next_source_rows_next205'] !== []
) {
    fwrite(STDERR, "wordpress-trigger-recursive-view-returning-current-source-next205 self-test failed\n");
    exit(1);
}

echo "wordpress-trigger-recursive-view-returning-current-source-next205 self-test passed\n";
