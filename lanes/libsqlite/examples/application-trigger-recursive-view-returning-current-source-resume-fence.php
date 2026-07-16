<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$currentView = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-190-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-190-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    'audit_label' => 'current-recursive-view-trigger-190',
];
$nextView = $currentView;
$nextView['source'] = 'main@view-cookie-190-next';
$nextView['trigger_source'] = 'main@trigger-cookie-190-next';
$nextView['audit_label'] = 'next-recursive-view-trigger-190';

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentSourceResumeFence(
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
        ['expr' => 'event', 'as' => 'event_name'],
        ['expr' => 'depth', 'as' => 'depth_value'],
        ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ],
    [
        'savepoint' => 'app_recursive_view_190',
        'cursor_name' => 'app_recursive_view_returning_cursor_190',
        'checkpoint_name' => 'app_recursive_view_checkpoint_190',
        'admit_next_source' => true,
        'auto_ack_current' => true,
        'page_size' => 3,
    ],
);

if (
    $summary['status'] !== 'trigger-recursive-view-returning-current-source-next190-next-exposed'
    || $summary['resume_plan']['last_current_resume_token'] !== 'app_recursive_view_returning_cursor_190:app-current-returning-190:5'
    || $summary['resume_source_matches'] !== true
    || $summary['next_source_resume_matches'] !== true
    || $summary['source_signature_matches'] !== true
    || count($summary['visible_returning_rows']) !== 10
    || $summary['held_rows'] !== []
) {
    fwrite(STDERR, "application-trigger-recursive-view-returning-current-source-next190 self-test failed\n");
    exit(1);
}

echo "application-trigger-recursive-view-returning-current-source-next190 self-test passed\n";
