<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$plan = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::insertThroughViewSources(
    [['setting_id' => 10, 'key_name' => 'base_url', 'depth' => 0, 'load_policy' => 'yes']],
    [['setting_id' => 20, 'key_name' => 'module_current', 'depth' => 1, 'load_policy' => 'yes']],
    [['setting_id' => 30, 'key_name' => 'module_next', 'depth' => 1, 'load_policy' => 'no']],
    [[
        'timing' => 'after',
        'event' => 'insert',
        'table' => 'target',
        'action' => 'insert',
        'when' => ['column' => 'depth', 'operator' => '<', 'value' => 3],
        'insert_row' => [
            'setting_id' => 'new_increment.setting_id',
            'key_name' => 'concat:new.key_name::child',
            'depth' => 'new_increment.depth',
            'load_policy' => 'new.load_policy',
        ],
    ]],
    ['key_name'],
    ['new.setting_id', ['expr' => 'key_name', 'as' => 'name'], 'depth'],
    [
        'view' => 'app_setting_import_view',
        'savepoint' => 'app_import_next143',
        'current_source' => 'app-options@before-current-recursive-view-import',
        'next_source' => 'app-options@after-next-recursive-view-import',
    ],
);

$summary = [
    'scenario' => 'application-trigger-recursive-view-returning-current-source-next143',
    'applicationUse' => 'A copied app_settings import can yield RETURNING rows from an INSTEAD OF view trigger chain before rolling the current source back to a savepoint, then admit the next source from the saved image.',
    'status' => $plan['status'],
    'nextInput' => $plan['source_transition']['next_input'],
    'admittedNext' => array_column($plan['returning_rows'], 'name'),
    'suppressedCurrent' => array_column($plan['suppressed_returning_rows'], 'name'),
    'finalOptions' => array_column($plan['final_rows'], 'key_name'),
    'discardedReturning' => $plan['discarded_returning_count'],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['status'] === 'current-recursive-view-rollback-next-source-admitted');
    assert($summary['nextInput'] === 'saved-current-source');
    assert($summary['admittedNext'] === ['module_next', 'module_next:child', 'module_next:child:child']);
    assert($summary['suppressedCurrent'] === ['module_current', 'module_current:child', 'module_current:child:child']);
    assert($summary['finalOptions'] === ['base_url', 'module_next', 'module_next:child', 'module_next:child:child']);
    assert($summary['discardedReturning'] === 3);
    echo "application-trigger-recursive-view-returning-current-source-next143 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
