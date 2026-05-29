<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'source' => 'seed'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'source' => 'seed'],
];
$view = [
    'name' => 'wp_option_import_view',
    'source' => 'main@view-cookie-178-current',
    'trigger' => 'wp_option_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-178-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_retry',
    'audit_label' => 'current-recursive-trigger-drain-178',
];
$nextView = $view;
$nextView['source'] = 'main@view-cookie-178-next';
$nextView['trigger_source'] = 'main@trigger-cookie-178-next';
$nextView['columns'] = ['import_id', 'name', 'value', 'autoload_flag', 'origin'];
$nextView['mapping']['origin'] = 'source';
$nextView['recursive_suffix'] = '_next_retry';
$nextView['audit_label'] = 'next-recursive-trigger-drain-178';

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNextSourcePublicationFence(
    $rows,
    [
        ['import_id' => 10, 'name' => 'plugin_seed', 'value' => 'enabled', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ['import_id' => 12, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
    ],
    [
        ['import_id' => 20, 'name' => 'rewrite_rules', 'value' => 'cached', 'autoload_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => true],
        ['import_id' => 21, 'name' => 'home', 'value' => 'https://next-home.test', 'autoload_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => false],
    ],
    $view,
    $nextView,
    ['new.option_name', ['expr' => 'new.option_value', 'as' => 'value'], ['expr' => 'trigger_source', 'as' => 'trigger_cookie']],
    [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_178',
        'max_depth' => 2,
        'page_size' => 2,
        'drained_current_pages' => 2,
        'savepoint_action' => 'release',
        'current_source_epoch' => 8,
        'restart_cursor' => 'wp-recursive-view-returning-restart-178',
        'snapshot_token' => 'wp.recursive.view.returning.snapshot.178',
        'expected_snapshot_token' => 'wp.recursive.view.returning.snapshot.178',
        'current_schema_cookie' => 178,
        'expected_current_schema_cookie' => 178,
    ],
);

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if (($summary['status_next178'] ?? null) !== 'trigger-recursive-view-returning-current-source-snapshot-released-next178') {
        fwrite(STDERR, "unexpected recursive view RETURNING next178 status\n");
        exit(1);
    }
    if (($summary['returning_snapshot_plan_next178']['decision'] ?? null) !== 'publish-current-then-next-returning') {
        fwrite(STDERR, "recursive view RETURNING next178 snapshot did not publish current then next rows\n");
        exit(1);
    }
    if (($summary['statement_returning_row_count_next178'] ?? null) !== 8) {
        fwrite(STDERR, "recursive view RETURNING next178 statement row count changed\n");
        exit(1);
    }
    echo "wordpress-trigger-recursive-view-returning-current-source-next178 self-test passed\n";
}

return [
    'scenario' => 'wordpress-trigger-recursive-view-returning-current-source-next178',
    'wordpressUse' => 'Copied wp_options imports through an INSTEAD OF recursive view trigger keep RETURNING rows tied to the current-source snapshot and view schema cookie before a savepoint release exposes queued next-source rows.',
    'dependencyClosure' => 'no new support component needed; reuses native PHP recursive view RETURNING current-source savepoint and schema-cookie modeling',
    'summary' => [
        'status' => $summary['status_next178'],
        'decision' => $summary['returning_snapshot_plan_next178']['decision'],
        'visibleRows' => $summary['statement_returning_row_count_next178'],
        'currentRows' => $summary['current_returning_row_count_next178'],
        'nextRows' => $summary['next_returning_row_count_next178'],
        'sourceOrder' => $summary['returning_source_order_next178'],
    ],
];
