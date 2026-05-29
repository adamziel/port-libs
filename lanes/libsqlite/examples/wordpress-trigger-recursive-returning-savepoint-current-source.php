<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan;

$plan = SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan::insertRowsWithinSavepoint(
    [['option_id' => 40, 'option_name' => 'existing', 'level' => 0, 'autoload' => 'no']],
    [['option_id' => 1, 'option_name' => 'plugin_seed', 'level' => 1, 'autoload' => 'yes']],
    [[
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
    ]],
    ['option_name'],
    ['new.option_id', ['expr' => 'option_name', 'as' => 'name'], 'level'],
    [
        'savepoint' => 'wp_recursive_import',
        'current_source' => 'wordpress-current-recursive-trigger-returning',
        'next_source' => 'wordpress-next-after-rollback-to',
    ],
);

echo json_encode([
    'savepoint' => $plan['savepoint'],
    'rolled_back' => $plan['rolled_back'],
    'returning_names' => array_column($plan['returning_rows'], 'name'),
    'after_savepoint_names' => array_column($plan['after_savepoint'], 'option_name'),
    'discarded_returning_count' => $plan['discarded_returning_count'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
