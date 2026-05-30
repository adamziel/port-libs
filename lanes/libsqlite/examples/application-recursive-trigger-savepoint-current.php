<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRecursiveTriggerSavepointPlan;

$savepointRows = [
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
        'option_name' => 'preflight_marker',
        'level' => 'new_increment.level',
        'autoload' => 'new.autoload',
    ],
    'conflict_action' => 'rollback',
]];

$plan = SQLiteRecursiveTriggerSavepointPlan::insertRows(
    'plugin-option-import',
    $savepointRows,
    [['option_id' => 10, 'option_name' => 'plugin_seed', 'level' => 1, 'autoload' => 'yes']],
    $trigger,
    ['option_name'],
    'rollback'
);

echo json_encode([
    'savepoint' => $plan['savepoint'],
    'rollbackScope' => $plan['rollback_scope'],
    'currentOptionNames' => array_column($plan['current_rows'], 'option_name'),
    'discardedOptionNames' => array_column($plan['discarded'], 'option_name'),
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
