<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wp-import');
$savepoints->recordPageImageWrite(1, $page('before-import-root'));
$savepoints->recordWalFrameWrite(1, 1);
$savepoints->savepoint('plugin-options');
$savepoints->recordPageImageWrite(2, $page('before-plugin-option'));
$savepoints->recordWalFrameWrite(2, 2);
$savepoints->beginStatementJournal('insert-duplicate-option');
$savepoints->recordStatementPageImageWrite('insert-duplicate-option', 3, $page('before-statement-table'));
$savepoints->recordStatementWalFrameWrite('insert-duplicate-option', 3, 3);
$savepoints->recordStatementPageImageWrite('insert-duplicate-option', 4, $page('before-statement-index'));
$savepoints->recordStatementWalFrameWrite('insert-duplicate-option', 4, 4, true);

$dirtyDatabase = $page('dirty-import-root') . $page('dirty-plugin-option') . $page('dirty-statement-table') . $page('dirty-statement-index');
$rolledBack = $savepoints->rollbackStatementDatabaseImage('insert-duplicate-option', $dirtyDatabase, $pageSize);
$plan = $savepoints->rollbackStatementOnErrorWithPlan('insert-duplicate-option', $pageSize);

echo json_encode([
    'status' => 'statement_error_rolled_back',
    'statement' => $plan['statement'],
    'savepoint' => $plan['savepoint'],
    'restored_page_numbers' => $plan['restored_page_numbers'],
    'rollback_to_wal_frame' => $plan['rollback_to_wal_frame'],
    'discarded_wal_frames' => array_column($plan['discarded_wal_frames'], 'frame_index'),
    'active_savepoints' => $savepoints->names(),
    'pending_wal_frames' => $savepoints->pendingWalFrameIndexes(),
    'statement_journals' => $savepoints->statementJournalState(),
    'page_3_prefix' => rtrim(substr($rolledBack, $pageSize * 2, 32), '.'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
