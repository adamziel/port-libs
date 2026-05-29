<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$currentView = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-194-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-194-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload'],
    'audit_label' => 'current-recursive-view-trigger-194',
];
$nextView = $currentView;
$nextView['source'] = 'main@view-cookie-194-next';
$nextView['trigger_source'] = 'main@trigger-cookie-194-next';
$nextView['audit_label'] = 'next-recursive-view-trigger-194';

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentSourceDoneGate(
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
        'savepoint' => 'wp_recursive_view_194',
        'cursor_name' => 'wp_recursive_view_returning_cursor_194',
        'checkpoint_name' => 'wp_recursive_view_checkpoint_194',
        'page_size' => 3,
    ],
);

if (
    $summary['status'] !== 'trigger-recursive-view-returning-current-source-next194-next-exposed'
    || $summary['current_result_code_next194'] !== 'SQLITE_DONE'
    || $summary['next_source_exposed_after_current_done_next194'] !== true
    || $summary['current_done_plan_next194']['decision'] !== 'admit-next-source-after-current-done'
    || count($summary['visible_returning_rows']) !== 10
    || $summary['held_rows'] !== []
) {
    fwrite(STDERR, "wordpress-trigger-recursive-view-returning-current-source-next194 self-test failed\n");
    exit(1);
}

echo "wordpress-trigger-recursive-view-returning-current-source-next194 self-test passed\n";
