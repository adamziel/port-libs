<?php

declare(strict_types=1);

require dirname(__DIR__) . '/../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSavepointStack;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wp_options_import');
$savepoints->recordPageImageWrite(1, $page('before-site-options'));
$savepoints->recordWalFrameWrite(1, 1);
$savepoints->savepoint('plugin_settings');
$savepoints->recordPageImageWrite(2, $page('before-plugin-row'));
$savepoints->recordWalFrameWrite(2, 2);
$savepoints->savepoint('autoload_index');
$savepoints->recordPageImageWrite(3, $page('before-autoload-index'));
$savepoints->recordWalFrameWrite(3, 3, true);
$savepoints->savepoint('cache_warmup');
$savepoints->recordPageImageWrite(4, $page('before-cache-row'));
$savepoints->recordWalFrameWrite(4, 4);

$release = $savepoints->releaseWithPlan('autoload_index');
$dirtyDatabase = $page('dirty-site-options')
    . $page('dirty-plugin-row')
    . $page('dirty-autoload-index')
    . $page('dirty-cache-row');
$rolledBack = $savepoints->rollbackToDatabaseImage('plugin_settings', $dirtyDatabase, $pageSize);
$walRollback = $savepoints->walRollbackToPlan('plugin_settings');
$commit = $savepoints->commitWithPlan();

echo json_encode([
    'release_status' => $release['transaction_active_after'] ? 'outer_transaction_active' : 'committed',
    'released_savepoints' => $release['released_frame_names'],
    'merged_page_numbers' => $release['merged_page_numbers'],
    'rollback_restored_plugin_page' => rtrim(substr($rolledBack, $pageSize, $pageSize), '.'),
    'rollback_restored_cache_page' => rtrim(substr($rolledBack, $pageSize * 3, $pageSize), '.'),
    'wal_discarded_frame_indexes' => array_column($walRollback['discarded_wal_frames'], 'frame_index'),
    'commit_pages' => $commit['committed_page_numbers'],
    'transaction_active_after_commit' => $commit['transaction_active_after'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
