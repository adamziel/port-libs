<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$databaseDigest = hash('sha256', 'application next258 checkpoint database');
$pageCacheDigest = hash('sha256', 'application next258 clean cache');
$readerPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next255',
    'restarted_reader_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/application.sqlite',
    'wal_path' => '/srv/www/wp-content/database/application.sqlite-wal',
    'source_token' => 'application-next258-source',
    'commit_generation' => 258,
    'checkpoint_frame' => 0,
    'database_digest' => $databaseDigest,
    'page_cache_digest' => $pageCacheDigest,
    'next_wal_salt' => ['00000102', '00000304'],
    'reopened_reader_names' => ['front-page', 'plugin-cache', 'wp-options-import'],
    'readmark_slots' => [1, 2, 3],
    'operation_names' => ['admit_restarted_wal_readers_next255'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next255'],
];

$baseReceipt = [
    'database_path' => $readerPlan['database_path'],
    'wal_path' => $readerPlan['wal_path'],
    'source_token' => $readerPlan['source_token'],
    'commit_generation' => 259,
    'checkpoint_frame' => 0,
    'database_digest' => $databaseDigest,
    'page_cache_digest' => $pageCacheDigest,
    'old_wal_salt' => ['00000102', '00000304'],
    'new_wal_salt' => ['00000506', '00000708'],
    'reader_names' => $readerPlan['reopened_reader_names'],
    'readmark_slots' => $readerPlan['readmark_slots'],
    'wal_size_before' => 32,
    'first_frame_index' => 1,
    'first_frame_page' => 2,
    'mx_frame_before' => 0,
    'mx_frame_after' => 1,
    'exclusive_lock_held' => true,
    'hot_journal_visible' => false,
    'pending_savepoint_depth' => 0,
    'durably_synced' => true,
    'io_error' => null,
];

$receipts = [
    array_replace($baseReceipt, ['name' => 'application-next258-header-salt', 'kind' => 'header-salt']),
    array_replace($baseReceipt, ['name' => 'application-next258-first-frame', 'kind' => 'first-frame']),
    array_replace($baseReceipt, ['name' => 'application-next258-reader-fence', 'kind' => 'reader-fence']),
    array_replace($baseReceipt, ['name' => 'application-next258-sync', 'kind' => 'sync']),
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next258AdmitWriterAfterRestartedReaders($readerPlan, $receipts);

echo json_encode([
    'status' => $plan['status'],
    'writer_action' => $plan['writer_action'],
    'wal_action' => $plan['wal_action'],
    'reader_action' => $plan['reader_action'],
    'journal_action' => $plan['journal_action'],
    'blocked_reasons' => $plan['blocked_writer_reasons'],
    'admission_digest' => $plan['admission_digest'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
