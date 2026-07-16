<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteConnectionCounters;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$generatedOptionId = isset($argv[1]) ? (int) $argv[1] : 42;
$updatedRows = isset($argv[2]) ? (int) $argv[2] : 2;

$counters = SQLiteConnectionCounters::initial();
$beforeImport = $counters->toArray();

$counters->recordInsert($generatedOptionId);
$afterGeneratedInsert = $counters->toArray();

$savepointSnapshot = $counters->snapshot();
$counters->recordUpdate($updatedRows);
$afterAutoloadUpdate = $counters->toArray();

$counters->recordInsert($generatedOptionId + 1);
$counters->recordDelete(3);
$beforeSavepointRollback = $counters->toArray();
$savepointRollbackPlan = $counters->preserveAfterSavepointRollback($savepointSnapshot);
$afterSavepointRollback = $counters->toArray();

echo json_encode([
    'applicationUse' => 'Preview SQLite last_insert_rowid(), changes(), and total_changes() counters for copied wp_options insert/update batches and savepoint rollback diagnostics, including ROLLBACK TO savepoint preserving the most recent DML changes() value, connection-total counters, and successful insert rowids, without requiring ext-sqlite.',
    'beforeImport' => $beforeImport,
    'afterGeneratedInsert' => $afterGeneratedInsert,
    'afterAutoloadUpdate' => $afterAutoloadUpdate,
    'beforeSavepointRollback' => $beforeSavepointRollback,
    'savepointRollbackPlan' => $savepointRollbackPlan,
    'afterSavepointRollback' => $afterSavepointRollback,
    'sqlFunctions' => [
        'last_insert_rowid' => $counters->sqlFunctionArguments('last_insert_rowid', []),
        'changes' => $counters->sqlFunctionArguments('changes', []),
        'total_changes' => $counters->sqlFunctionArguments('total_changes', []),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
