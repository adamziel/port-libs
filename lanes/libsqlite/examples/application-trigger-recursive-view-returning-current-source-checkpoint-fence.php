<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'source' => 'seed'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes', 'source' => 'seed'],
];
$view = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-175-current',
    'trigger' => 'app_setting_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-175-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_retry',
    'audit_label' => 'current-recursive-trigger-drain-175',
];
$nextView = $view;
$nextView['source'] = 'main@view-cookie-175-next';
$nextView['trigger_source'] = 'main@trigger-cookie-175-next';
$nextView['columns'] = ['import_id', 'name', 'value', 'load_policy_flag', 'origin'];
$nextView['mapping']['origin'] = 'source';
$nextView['recursive_suffix'] = '_next_retry';
$nextView['audit_label'] = 'next-recursive-trigger-drain-175';

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentSourceCheckpointFence(
    $rows,
    [
        ['import_id' => 10, 'name' => 'module_seed', 'value' => 'enabled', 'load_policy_flag' => 'yes', 'spawn_child' => true],
        ['import_id' => 12, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
    ],
    [
        ['import_id' => 20, 'name' => 'routing_rules', 'value' => 'cached', 'load_policy_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => true],
        ['import_id' => 21, 'name' => 'landing_url', 'value' => 'https://next-landing_url.test', 'load_policy_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => false],
    ],
    $view,
    $nextView,
    ['new.key_name', ['expr' => 'new.key_value', 'as' => 'value'], ['expr' => 'trigger_source', 'as' => 'trigger_cookie']],
    [
        'key' => 'key_name',
        'savepoint' => 'app_recursive_view_175',
        'max_depth' => 2,
        'page_size' => 2,
        'drained_current_pages' => 2,
        'savepoint_action' => 'release',
        'current_source_epoch' => 7,
        'restart_cursor' => 'app-recursive-view-returning-restart-175',
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
    'applicationUse' => 'Copied app_settings imports through an INSTEAD OF recursive view trigger can drain current-source RETURNING pages before a savepoint release exposes queued next-source rows, while rollback keeps the next source fenced.',
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
