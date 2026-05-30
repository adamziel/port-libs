<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteDmlTriggerRecursionPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$summary = SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentSourceDrainBeforeNextYield(
    [['option_id' => 1, 'option_name' => 'siteurl', 'depth' => 0, 'autoload' => 'yes']],
    [['option_id' => 10, 'option_name' => 'current_plugin', 'depth' => 0, 'autoload' => 'yes']],
    [['option_id' => 20, 'option_name' => 'next_plugin', 'depth' => 0, 'autoload' => 'no']],
    [[
        'timing' => 'after',
        'event' => 'insert',
        'table' => 'target',
        'action' => 'insert',
        'when' => ['column' => 'depth', 'operator' => '<', 'value' => 2],
        'insert_row' => [
            'option_id' => 'new_increment.option_id',
            'option_name' => 'concat:new.option_name::child',
            'depth' => 'new_increment.depth',
            'autoload' => 'new.autoload',
        ],
    ]],
    ['option_name'],
    ['new.option_id', ['expr' => 'option_name', 'as' => 'name'], 'depth'],
    [
        'view' => 'wp_recursive_option_view',
        'savepoint' => 'wp_recursive_view_next154',
        'current_source' => 'main@trigger154-current',
        'next_source' => 'main@trigger154-next',
        'current_cursor' => 'wp_current_returning_cursor_154',
        'next_cursor' => 'wp_next_returning_cursor_154',
        'acknowledged_current_rows' => 3,
    ],
);

$result = [
    'scenario' => 'application-trigger-recursive-view-returning-current-source-next154',
    'applicationUse' => 'Copied wp_options imports through recursive INSTEAD OF view triggers hold next-source RETURNING rows until the current-source cursor is fully drained and acknowledged.',
    'status' => $summary['status'],
    'currentRowsRequired' => $summary['current_returning_required'],
    'visibleNextNames' => array_column($summary['visible_next_returning_rows'], 'name'),
    'dependencyClosure' => 'no new support component needed; reuses native recursive trigger RETURNING current-source drain and cursor handoff modeling',
];

if (in_array('--self-test', $argv, true)) {
    if (
        $result['status'] !== 'trigger-recursive-view-returning-current-source-drained-next-yield-visible'
        || $result['currentRowsRequired'] !== 3
        || $result['visibleNextNames'] !== ['next_plugin', 'next_plugin:child', 'next_plugin:child:child']
    ) {
        fwrite(STDERR, "application-trigger-recursive-view-returning-current-source-next154 self-test failed\n");
        exit(1);
    }

    echo "application-trigger-recursive-view-returning-current-source-next154 self-test passed\n";
    return;
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
