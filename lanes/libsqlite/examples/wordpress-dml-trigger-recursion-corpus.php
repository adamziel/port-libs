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

echo json_encode([
    'wordpressUse' => 'Preview bounded recursive INSERT triggers for copied wp_options import/audit rows without requiring ext/sqlite.',
    'optionNames' => array_column($result['rows'], 'option_name'),
    'levels' => array_column($result['rows'], 'level'),
    'changes' => $result['changes'],
    'recursiveTriggers' => $result['recursive_triggers'],
    'maxDepth' => $result['max_depth'],
    'triggerResults' => array_column($result['effects'], 'result'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
