<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteDmlTriggerRecursionPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentSourceDrainBeforeNextYield(
    [['setting_id' => 1, 'key_name' => 'base_url', 'depth' => 0, 'load_policy' => 'yes']],
    [['setting_id' => 10, 'key_name' => 'current_module', 'depth' => 0, 'load_policy' => 'yes']],
    [['setting_id' => 20, 'key_name' => 'next_module', 'depth' => 0, 'load_policy' => 'no']],
    [[
        'timing' => 'after',
        'event' => 'insert',
        'table' => 'target',
        'action' => 'insert',
        'when' => ['column' => 'depth', 'operator' => '<', 'value' => 2],
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
        'view' => 'app_recursive_setting_view',
        'savepoint' => 'app_recursive_view_next154',
        'current_source' => 'main@trigger154-current',
        'next_source' => 'main@trigger154-next',
        'current_cursor' => 'app_current_returning_cursor_154',
        'next_cursor' => 'app_next_returning_cursor_154',
        'acknowledged_current_rows' => 3,
    ],
);

$result = [
    'scenario' => 'application-trigger-recursive-view-returning-current-source-next154',
    'applicationUse' => 'Copied app_settings imports through recursive INSTEAD OF view triggers hold next-source RETURNING rows until the current-source cursor is fully drained and acknowledged.',
    'status' => $summary['status'],
    'currentRowsRequired' => $summary['current_returning_required'],
    'visibleNextNames' => array_column($summary['visible_next_returning_rows'], 'name'),
    'dependencyClosure' => 'no new support component needed; reuses native recursive trigger RETURNING current-source drain and cursor handoff modeling',
];

if (in_array('--self-test', $argv, true)) {
    if (
        $result['status'] !== 'trigger-recursive-view-returning-current-source-drained-next-yield-visible'
        || $result['currentRowsRequired'] !== 3
        || $result['visibleNextNames'] !== ['next_module', 'next_module:child', 'next_module:child:child']
    ) {
        fwrite(STDERR, "application-trigger-recursive-view-returning-current-source-next154 self-test failed\n");
        exit(1);
    }

    echo "application-trigger-recursive-view-returning-current-source-next154 self-test passed\n";
    return;
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
