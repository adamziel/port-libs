<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDmlTriggerRecursionPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

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

$result = SQLiteDmlTriggerRecursionPlan::insertRows(
    [],
    [['option_id' => 1, 'option_name' => 'plugin_seed', 'level' => 1, 'autoload' => 'yes']],
    $trigger,
    ['option_name'],
    'abort',
    ['recursive_triggers' => true, 'max_depth' => 8]
);

$conflictTrigger = $trigger;
$conflictTrigger[0]['when']['value'] = 2;
$conflictTrigger[0]['insert_row']['option_name'] = 'plugin_seed';
$conflictTrigger[0]['insert_row']['level'] = 7;
$conflictTrigger[0]['conflict_action'] = 'fail';

$ignoreConflict = SQLiteDmlTriggerRecursionPlan::insertRows(
    [],
    [['option_id' => 1, 'option_name' => 'plugin_seed', 'level' => 1, 'autoload' => 'yes']],
    $conflictTrigger,
    ['option_name'],
    'ignore',
    ['recursive_triggers' => true, 'max_depth' => 8]
);

$replaceConflict = SQLiteDmlTriggerRecursionPlan::insertRows(
    [],
    [['option_id' => 1, 'option_name' => 'plugin_seed', 'level' => 1, 'autoload' => 'yes']],
    $conflictTrigger,
    ['option_name'],
    'replace',
    ['recursive_triggers' => true, 'max_depth' => 8]
);

echo json_encode([
    'applicationUse' => 'Preview bounded recursive INSERT triggers and outer statement conflict precedence for copied wp_options import/audit rows without requiring ext/sqlite.',
    'optionNames' => array_column($result['rows'], 'option_name'),
    'levels' => array_column($result['rows'], 'level'),
    'changes' => $result['changes'],
    'recursiveTriggers' => $result['recursive_triggers'],
    'maxDepth' => $result['max_depth'],
    'triggerResults' => array_column($result['effects'], 'result'),
    'outerIgnoreTriggerFail' => [
        'levels' => array_column($ignoreConflict['rows'], 'level'),
        'ignored' => array_column($ignoreConflict['ignored'], 'option_name'),
        'effectiveConflictActions' => array_values(array_unique(array_column($ignoreConflict['effects'], 'effective_conflict_action'))),
    ],
    'outerReplaceTriggerFail' => [
        'levels' => array_column($replaceConflict['rows'], 'level'),
        'changes' => $replaceConflict['changes'],
        'effectiveConflictActions' => array_values(array_unique(array_column($replaceConflict['effects'], 'effective_conflict_action'))),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
