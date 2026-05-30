<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$view = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-202-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-202-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-202',
];
$nextView = $view;
$nextView['source'] = 'main@view-cookie-202-next';
$nextView['trigger_source'] = 'main@trigger-cookie-202-next';
$nextView['audit_label'] = 'next-recursive-view-trigger-202';
$followingView = $view;
$followingView['source'] = 'main@view-cookie-202-following';
$followingView['trigger_source'] = 'main@trigger-cookie-202-following';
$followingView['audit_label'] = 'following-recursive-view-trigger-202';
$postResetView = $view;
$postResetView['source'] = 'main@view-cookie-202-post-reset';
$postResetView['trigger_source'] = 'main@trigger-cookie-202-post-reset';
$postResetView['audit_label'] = 'post-reset-recursive-view-trigger-202';

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentGenerationDepthFence(
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
        ['expr' => 'old.option_value', 'as' => 'old_value'],
        ['expr' => 'event', 'as' => 'event_name'],
        ['expr' => 'depth', 'as' => 'depth_value'],
        ['expr' => 'ordinal', 'as' => 'ordinal_value'],
        ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
        ['expr' => 'spawn_child', 'as' => 'spawn_child'],
    ],
    [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_202',
        'cursor_name' => 'wp_recursive_view_returning_cursor_202',
        'current_generation' => 'wp-current-returning-202',
        'next_generation' => 'wp-next-returning-202',
        'page_size' => 3,
        'admit_next_source' => true,
        'current_source_token' => 'wp.current.source.202',
        'drain_ack_token' => 'wp.returning.drain.202',
        'rollback_token' => 'wp.rollback.current.202',
        'reset_generation' => 'wp-current-reset-202',
        'post_reset_current_source_token' => 'wp.current.source.postreset.202',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.202',
        'post_reset_view' => $postResetView,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.202',
        'next_cursor' => 'wp.returning.next.cursor.202',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.202',
        'following_current_source_token' => 'wp.current.source.following.202',
        'following_cursor' => 'wp.returning.following.cursor.202',
        'following_current_view' => $followingView,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'wp-following-current-202',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.202',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.202',
        'recursive_child_generation' => 'wp-recursive-child-current-202',
        'current_view_generation_generationDepthFence' => 'wp.current.recursive.view.202',
        'expected_current_view_generation_generationDepthFence' => 'wp.current.recursive.view.202',
        'next_view_generation_generationDepthFence' => 'wp.next.recursive.view.202',
        'returning_resume_barrier_generationDepthFence' => 'wp.returning.resume.barrier.202',
        'required_current_depths_generationDepthFence' => [0, 1],
        'acknowledged_current_depths_generationDepthFence' => [0, 1],
    ],
);

if (
    ($summary['status_generationDepthFence'] ?? null) !== 'trigger-recursive-view-returning-current-source-generation-depth-fence-next-source-visible'
    || ($summary['visible_row_count_generationDepthFence'] ?? null) !== 7
    || ($summary['held_next_row_count_generationDepthFence'] ?? null) !== 0
) {
    fwrite(STDERR, "application-trigger-recursive-view-returning-current-source-generation-depth-fence self-test failed\n");
    exit(1);
}

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    echo "application-trigger-recursive-view-returning-current-source-generation-depth-fence self-test passed\n";
}

return [
    'scenario' => 'application-trigger-recursive-view-returning-current-source-generation-depth-fence',
    'applicationUse' => 'Copied wp_options imports through recursive INSTEAD OF view triggers keep RETURNING rows pinned to the current view generation and recursive depth acknowledgements before next-source rows become visible.',
    'dependencyClosure' => 'no new support component needed; reuses native recursive view RETURNING current-source and next196 child-drain modeling',
    'summary' => [
        'status' => $summary['status_generationDepthFence'],
        'visibleRows' => $summary['visible_row_count_generationDepthFence'],
        'heldNextRows' => $summary['held_next_row_count_generationDepthFence'],
        'requiredDepths' => $summary['required_current_depths_generationDepthFence'],
        'acknowledgedDepths' => $summary['acknowledged_current_depths_generationDepthFence'],
        'boundary' => $summary['yield_boundary_generationDepthFence'],
    ],
];
