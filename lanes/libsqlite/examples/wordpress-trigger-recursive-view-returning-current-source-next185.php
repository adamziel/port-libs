<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$view = [
    'name' => 'wp_option_import_view',
    'source' => 'main@view-cookie-185-current',
    'trigger' => 'wp_option_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-185-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_retry',
    'audit_label' => 'current-recursive-trigger-drain-185',
];
$nextView = $view;
$nextView['source'] = 'main@view-cookie-185-next';
$nextView['trigger_source'] = 'main@trigger-cookie-185-next';
$nextView['recursive_suffix'] = '_next_retry';
$nextView['audit_label'] = 'next-recursive-trigger-drain-185';

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext185(
    [
        ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
        ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
    ],
    [
        ['import_id' => 10, 'name' => 'plugin_seed', 'value' => 'enabled', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ['import_id' => 11, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
    ],
    [
        ['import_id' => 20, 'name' => 'rewrite_rules', 'value' => 'cached', 'autoload_flag' => 'yes', 'spawn_child' => true],
        ['import_id' => 21, 'name' => 'home', 'value' => 'https://next-home.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
    ],
    $view,
    $nextView,
    ['new.option_name', ['expr' => 'depth', 'as' => 'depth_value'], ['expr' => 'trigger_source', 'as' => 'trigger_cookie']],
    [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_185',
        'max_depth' => 2,
        'page_size' => 2,
        'drained_current_pages' => 2,
        'savepoint_action' => 'release',
        'current_source_epoch' => 18,
        'snapshot_token' => 'wp.recursive.view.returning.snapshot.185',
        'current_schema_cookie' => 185,
        'current_source_generation' => 'wp.recursive.view.current.185',
        'trigger_source_generation' => 'wp.recursive.trigger.current.185',
        'returning_cursor_generation' => 'wp.recursive.returning.cursor.185',
        'nested_epoch' => 'wp.recursive.view.nested.185',
        'required_nested_depths' => [1, 2],
        'drained_nested_depths' => [1, 2],
    ],
);

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if (($summary['status_next185'] ?? null) !== 'trigger-recursive-view-returning-current-source-nested-drained-next185') {
        fwrite(STDERR, "unexpected recursive view RETURNING nested drain status\n");
        exit(1);
    }
    if (($summary['visible_next_row_count_next185'] ?? null) !== 4) {
        fwrite(STDERR, "next-source RETURNING rows did not publish after nested current-source drain\n");
        exit(1);
    }
    if (($summary['nested_current_row_count_next185'] ?? null) !== 2) {
        fwrite(STDERR, "nested current-source RETURNING rows were not tracked\n");
        exit(1);
    }
    echo "wordpress-trigger-recursive-view-returning-current-source-next185 self-test passed\n";
}

return [
    'scenario' => 'wordpress-trigger-recursive-view-returning-current-source-next185',
    'wordpressUse' => 'Copied wp_options imports through an INSTEAD OF recursive view trigger can hold next-source RETURNING rows until nested recursive current-source rows at every required trigger depth have drained.',
    'dependencyClosure' => 'no new support component needed; reuses native PHP recursive view RETURNING current-source generation and nested-depth drain modeling',
    'summary' => $summary,
];
