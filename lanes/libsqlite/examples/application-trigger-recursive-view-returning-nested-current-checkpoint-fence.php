<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$base = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes'],
];
$currentView = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-181-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-181-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    'audit_label' => 'current-recursive-view-trigger-181',
];
$nextView = $currentView;
$nextView['source'] = 'main@view-cookie-181-next';
$nextView['trigger_source'] = 'main@trigger-cookie-181-next';
$nextView['audit_label'] = 'next-recursive-view-trigger-181';

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNestedCurrentCheckpointFence(
    $base,
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
        ['expr' => 'event', 'as' => 'event_name'],
        ['expr' => 'depth', 'as' => 'depth_value'],
        ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ],
    [
        'savepoint' => 'app_recursive_view_181',
        'cursor_name' => 'app_recursive_view_returning_cursor_181',
        'checkpoint_name' => 'app_recursive_view_checkpoint_181',
        'page_size' => 3,
    ],
);

if (
    $summary['status'] !== 'trigger-recursive-view-returning-current-source-next181-current-checkpointed-next-pending'
    || $summary['counts']['visible'] !== 2
    || $summary['counts']['pending'] !== 2
    || $summary['replay_plan']['resume_after_token'] !== 'app_recursive_view_returning_cursor_181:app-current-returning-181:5'
    || $summary['replay_plan']['blocked_at_token'] !== 'app_recursive_view_returning_cursor_181:app-next-returning-181:6'
    || $summary['visible_checkpoint_tokens'] !== [
        'app_recursive_view_checkpoint_181:app-current-returning-181:0',
        'app_recursive_view_checkpoint_181:app-current-returning-181:1',
    ]
) {
    fwrite(STDERR, "application-trigger-recursive-view-returning-current-source-next181 self-test failed\n");
    exit(1);
}

echo "application-trigger-recursive-view-returning-current-source-next181 self-test passed\n";
