<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wp_options_import');
$savepoints->recordPageWrite(1);
$savepoints->recordWalFrameWrite(1, 1);
$savepoints->recordWalFrameWrite(2, 2, true);
$savepoints->savepoint('plugin_settings');
$savepoints->recordPageWrite(3);
$savepoints->recordWalFrameWrite(3, 3);
$savepoints->savepoint('single_option');
$savepoints->recordPageWrite(4);
$savepoints->recordWalFrameWrite(4, 4);
$savepoints->recordWalFrameWrite(5, 5, true);

$plan = $savepoints->rollbackToCurrentAndRecordWalFrame('plugin_settings', 6, true);

echo json_encode([
    'scenario' => 'application-pager-savepoint-wal-retry',
    'legacyScenario' => 'application-pager-savepoint-current-next64',
    'applicationUse' => 'A copied wp_options plugin-settings import rolls back to the current savepoint, discards failed WAL tail frames, keeps the savepoint active, and records the next retry frame immediately after the retained WAL prefix without ext/sqlite.',
    'savepoint' => $plan['savepoint'],
    'rollbackToFrame' => $plan['rollback_to_frame'],
    'nextWalFrame' => $plan['next_wal_frame_index'],
    'discardedWalFrames' => array_column($plan['discarded_wal_frames'], 'frame_index'),
    'retainedWalFramesBeforeRetry' => $plan['retained_wal_frame_indexes_before_next'],
    'pendingWalFramesAfterRetry' => $plan['pending_wal_frame_indexes_after_next'],
    'pendingPagesAfterRetry' => $plan['pending_page_numbers_after_next'],
    'currentSavepointActiveAfter' => $plan['current_savepoint_active_after'],
    'transactionActiveAfter' => $plan['transaction_active_after'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
