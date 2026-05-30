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
    'source' => 'main@view-cookie-175-current',
    'trigger' => 'wp_option_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-175-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_retry',
    'audit_label' => 'current-recursive-trigger-drain-175',
];
$nextView = $view;
$nextView['source'] = 'main@view-cookie-175-next';
$nextView['trigger_source'] = 'main@trigger-cookie-175-next';
$nextView['columns'] = ['import_id', 'name', 'value', 'autoload_flag', 'origin'];
$nextView['mapping']['origin'] = 'source';
$nextView['recursive_suffix'] = '_next_retry';
$nextView['audit_label'] = 'next-recursive-trigger-drain-175';

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentSourceCheckpointFence(
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
        'savepoint' => 'wp_recursive_view_175',
        'max_depth' => 2,
        'page_size' => 2,
        'drained_current_pages' => 2,
        'savepoint_action' => 'release',
        'current_source_epoch' => 7,
        'restart_cursor' => 'wp-recursive-view-returning-restart-175',
    ],
);

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if (($summary['status_next175'] ?? null) !== 'trigger-recursive-view-returning-savepoint-released-next-source-next175') {
        fwrite(STDERR, "unexpected recursive view RETURNING next175 status\n");
        exit(1);
    }
    if (($summary['release_plan_next175']['decision'] ?? null) !== 'release-next-source') {
        fwrite(STDERR, "recursive view RETURNING next175 savepoint did not release the queued source\n");
        exit(1);
    }
    if (($summary['release_plan_next175']['visible_pages'] ?? null) !== 4) {
        fwrite(STDERR, "recursive view RETURNING next175 visible page count changed\n");
        exit(1);
    }
    echo "application-trigger-recursive-view-returning-current-source-next175 self-test passed\n";
}

return [
    'scenario' => 'application-trigger-recursive-view-returning-current-source-next175',
    'applicationUse' => 'Copied wp_options imports through an INSTEAD OF recursive view trigger can drain current-source RETURNING pages before a savepoint release exposes queued next-source rows, while rollback keeps the next source fenced.',
    'dependencyClosure' => 'no new support component needed; reuses native PHP recursive view RETURNING current-source savepoint modeling',
    'summary' => [
        'status' => $summary['status_next175'],
        'action' => $summary['savepoint_action_next175'],
        'releaseDecision' => $summary['release_plan_next175']['decision'],
        'visiblePages' => $summary['release_plan_next175']['visible_pages'],
        'queuedPages' => $summary['release_plan_next175']['queued_pages'],
        'restartFrom' => $summary['restart_plan_next175']['restart_from'],
    ],
];
