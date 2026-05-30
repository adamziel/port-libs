<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRecursiveTriggerSavepointCurrentNextPlan;

$savepointRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'level' => 0, 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'preflight_marker', 'level' => 0, 'autoload' => 'no'],
];
$currentRows = [
    ['option_id' => 10, 'option_name' => 'plugin_seed', 'level' => 1, 'autoload' => 'yes'],
];
$nextRows = [
    ['option_id' => 20, 'option_name' => 'plugin_retry', 'level' => 1, 'autoload' => 'yes'],
];
$trigger = [[
    'timing' => 'after',
    'event' => 'insert',
    'table' => 'target',
    'action' => 'insert',
    'when' => ['column' => 'level', 'operator' => '<', 'value' => 4],
    'insert_row' => [
        'option_id' => 'new_increment.option_id',
        'option_name' => 'concat:new.option_name::child',
        'level' => 'new_increment.level',
        'autoload' => 'new.autoload',
    ],
]];
$rollbackTrigger = $trigger;
$rollbackTrigger[0]['when']['value'] = 3;
$rollbackTrigger[0]['insert_row']['option_name'] = 'preflight_marker';
$rollbackTrigger[0]['conflict_action'] = 'rollback';

$plan = SQLiteRecursiveTriggerSavepointCurrentNextPlan::retryAfterRollback(
    'wp_import_sp',
    $savepointRows,
    $currentRows,
    $rollbackTrigger,
    $nextRows,
    $trigger,
    ['option_name'],
    [
        'option_id',
        ['expr' => 'new.option_name', 'as' => 'name'],
        ['expr' => 'depth', 'as' => 'trigger_depth'],
    ],
);

echo json_encode([
    'status' => $plan['status'],
    'currentRolledBack' => $plan['current_rolled_back'],
    'currentReason' => $plan['current_rollback_reason'],
    'discardedCurrentNames' => array_column($plan['discarded_current_rows'], 'option_name'),
    'nextNames' => array_column($plan['next_rows'], 'option_name'),
    'nextReturning' => $plan['next_returning_rows'],
    'totalChanges' => $plan['total_changes'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
