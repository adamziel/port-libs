<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$view = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-202-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-202-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'spawn_child' => 'spawn_child'],
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
        ['expr' => 'old.key_value', 'as' => 'old_value'],
        ['expr' => 'event', 'as' => 'event_name'],
        ['expr' => 'depth', 'as' => 'depth_value'],
        ['expr' => 'ordinal', 'as' => 'ordinal_value'],
        ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
        ['expr' => 'spawn_child', 'as' => 'spawn_child'],
    ],
    [
        'key' => 'key_name',
        'savepoint' => 'app_recursive_view_202',
        'cursor_name' => 'app_recursive_view_returning_cursor_202',
        'current_generation' => 'app-current-returning-202',
        'next_generation' => 'app-next-returning-202',
        'page_size' => 3,
        'admit_next_source' => true,
        'current_source_token' => 'app.current.source.202',
        'drain_ack_token' => 'app.returning.drain.202',
        'rollback_token' => 'app.rollback.current.202',
        'reset_generation' => 'app-current-reset-202',
        'post_reset_current_source_token' => 'app.current.source.postreset.202',
        'post_reset_cursor' => 'app.returning.postreset.cursor.202',
        'post_reset_view' => $postResetView,
        'post_reset_input' => [
            ['import_id' => 30, 'name' => 'base_url', 'value' => 'https://fresh.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 31, 'name' => 'routing_rules', 'value' => 'fresh-rules', 'load_policy_flag' => 'no', 'spawn_child' => false],
        ],
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'app.next.source.202',
        'next_cursor' => 'app.returning.next.cursor.202',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'app.returning.next.cursor.202',
        'following_current_source_token' => 'app.current.source.following.202',
        'following_cursor' => 'app.returning.following.cursor.202',
        'following_current_view' => $followingView,
        'following_current_input' => [
            ['import_id' => 40, 'name' => 'app_summary', 'value' => 'after-next', 'load_policy_flag' => 'yes', 'spawn_child' => true],
            ['import_id' => 41, 'name' => 'theme_style_key', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => false],
            ['import_id' => 42, 'name' => 'template', 'value' => 'modern_theme', 'load_policy_flag' => 'yes', 'spawn_child' => true],
        ],
        'following_generation' => 'app-following-current-202',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'app.current.source.recursive.child.202',
        'recursive_child_cursor' => 'app.returning.recursive.child.cursor.202',
        'recursive_child_generation' => 'app-recursive-child-current-202',
        'current_view_generation_generationDepthFence' => 'app.current.recursive.view.202',
        'expected_current_view_generation_generationDepthFence' => 'app.current.recursive.view.202',
        'next_view_generation_generationDepthFence' => 'app.next.recursive.view.202',
        'returning_resume_barrier_generationDepthFence' => 'app.returning.resume.barrier.202',
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
    'applicationUse' => 'Copied app_settings imports through recursive INSTEAD OF view triggers keep RETURNING rows pinned to the current view generation and recursive depth acknowledgements before next-source rows become visible.',
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
