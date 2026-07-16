<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRecursiveTriggerConflictRollbackPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$committedRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'level' => 0, 'autoload' => 'yes'],
];
$transactionRows = [
    ...$committedRows,
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

$result = SQLiteRecursiveTriggerConflictRollbackPlan::insertRows(
    $transactionRows,
    [['option_id' => 10, 'option_name' => 'plugin_seed', 'level' => 1, 'autoload' => 'yes']],
    $trigger,
    ['option_name'],
    'rollback',
    ['rollback_rows' => $committedRows]
);

echo json_encode([
    'scenario' => 'application-recursive-trigger-conflict-rollback',
    'applicationUse' => 'Preview copied wp_options plugin imports where a recursive trigger UNIQUE conflict uses SQLite OR ROLLBACK semantics to restore the pre-transaction image instead of keeping prestatement rows, without requiring ext/sqlite.',
    'finalOptionNames' => array_column($result['rows'], 'option_name'),
    'rolledBack' => $result['rolled_back'],
    'aborted' => $result['aborted'],
    'rollbackScope' => $result['rollback_scope'],
    'rollbackReason' => $result['rollback_reason'],
    'changes' => $result['changes'],
    'effectResults' => array_column($result['effects'], 'result'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
