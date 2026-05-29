<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$base = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-180-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-180-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload'],
    'audit_label' => 'current-recursive-view-trigger-180',
];
$nextView = $currentView;
$nextView['source'] = 'main@view-cookie-180-next';
$nextView['trigger_source'] = 'main@trigger-cookie-180-next';
$nextView['columns'] = ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child', 'import_source'];
$nextView['mapping']['import_source'] = 'source';
$nextView['audit_label'] = 'next-recursive-view-trigger-180';

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext180(
    $base,
    [
        ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
    ],
    [
        ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true, 'import_source' => 'next-cookie'],
        ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false, 'import_source' => 'next-cookie'],
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
        'savepoint' => 'wp_recursive_view_180',
        'cursor_name' => 'wp_recursive_view_returning_cursor_180',
        'page_size' => 3,
    ],
);

if (
    $summary['status_next180'] !== 'trigger-recursive-view-returning-current-source-next180-current-source-held'
    || $summary['source_changed_next180'] !== true
    || $summary['source_snapshot_next180']['current_rows_visible'] !== 6
    || $summary['source_snapshot_next180']['held_next_rows'] !== 4
    || array_column($summary['visible_returning_rows_next180'], 'name') !== ['siteurl', 'current_plugin', 'siteurl:child', 'current_plugin:child', 'siteurl:child:child', 'current_plugin:child:child']
    || array_column($summary['held_returning_rows_next180'], 'name') !== ['home', 'next_plugin', 'home:child', 'home:child:child']
) {
    fwrite(STDERR, "wordpress-trigger-recursive-view-returning-current-source-next180 self-test failed\n");
    exit(1);
}

echo "wordpress-trigger-recursive-view-returning-current-source-next180 self-test passed\n";
