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
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-190-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-190-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload'],
    'audit_label' => 'current-recursive-view-trigger-190',
];
$nextView = $currentView;
$nextView['source'] = 'main@view-cookie-190-next';
$nextView['trigger_source'] = 'main@trigger-cookie-190-next';
$nextView['audit_label'] = 'next-recursive-view-trigger-190';

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext190(
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
        ['expr' => 'event', 'as' => 'event_name'],
        ['expr' => 'depth', 'as' => 'depth_value'],
        ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ],
    [
        'savepoint' => 'wp_recursive_view_190',
        'cursor_name' => 'wp_recursive_view_returning_cursor_190',
        'checkpoint_name' => 'wp_recursive_view_checkpoint_190',
        'admit_next_source' => true,
        'auto_ack_current' => true,
        'page_size' => 3,
    ],
);

if (
    $summary['status'] !== 'trigger-recursive-view-returning-current-source-next190-next-exposed'
    || $summary['resume_plan']['last_current_resume_token'] !== 'wp_recursive_view_returning_cursor_190:wp-current-returning-190:5'
    || $summary['resume_source_matches'] !== true
    || $summary['next_source_resume_matches'] !== true
    || $summary['source_signature_matches'] !== true
    || count($summary['visible_returning_rows']) !== 10
    || $summary['held_rows'] !== []
) {
    fwrite(STDERR, "wordpress-trigger-recursive-view-returning-current-source-next190 self-test failed\n");
    exit(1);
}

echo "wordpress-trigger-recursive-view-returning-current-source-next190 self-test passed\n";
