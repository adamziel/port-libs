<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRecursiveTriggerReturningSavepointPlan;

$current = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'level' => 0, 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'preflight_marker', 'level' => 0, 'autoload' => 'no'],
];

$trigger = [[
    'timing' => 'after',
    'event' => 'insert',
    'table' => 'target',
    'action' => 'insert',
    'when' => ['column' => 'level', 'operator' => '<', 'value' => 3],
    'insert_row' => [
        'option_id' => 'new_increment.option_id',
        'option_name' => 'concat:new.option_name::child',
        'level' => 'new_increment.level',
        'autoload' => 'new.autoload',
    ],
]];

$plan = SQLiteRecursiveTriggerReturningSavepointPlan::insertRows(
    'wp-option-import',
    $current,
    [['option_id' => 10, 'option_name' => 'plugin_seed', 'level' => 1, 'autoload' => 'yes']],
    $trigger,
    ['option_name'],
    'abort',
    [
        'option_id',
        ['expr' => 'new.option_name', 'as' => 'name'],
        ['expr' => 'depth', 'as' => 'trigger_depth'],
    ]
);

echo json_encode([
    'savepoint' => $plan['savepoint'],
    'changes' => $plan['changes'],
    'rolled_back' => $plan['rolled_back'],
    'returning' => $plan['returning_rows'],
    'attemptedYieldCount' => count($plan['attempted_yields']),
    'nextRowid' => $plan['next_rowid'],
    'dependency' => 'sqlite-returning-recursive-yield-current-next50',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
