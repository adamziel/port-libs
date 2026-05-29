<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$digest = static fn (string $value): string => hash('sha256', $value);
$sourceToken = 'next228:wp-import-durable-source';
$databaseDigest = $digest('wordpress copied options database after durable checkpoint');

$publication = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next224',
    'publication_allowed' => true,
    'checkpoint_reset_visible' => true,
    'mode' => 'restart',
    'source_token' => $sourceToken,
    'next_writer_generation' => 228,
    'database_digest' => $databaseDigest,
    'operation_names' => ['publish_checkpoint_reset_current_source_next224'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next224'],
];

$barriers = [
    ['name' => 'wp-main-db-fsync', 'type' => 'database_sync', 'source_token' => $sourceToken, 'generation' => 228, 'sync_order' => 1, 'receipt' => true, 'exclusive_lock' => true, 'database_digest' => $databaseDigest],
    ['name' => 'wp-wal-restart-fsync', 'type' => 'wal_reset_sync', 'source_token' => $sourceToken, 'generation' => 228, 'sync_order' => 2, 'receipt' => true, 'exclusive_lock' => true, 'mode' => 'restart', 'wal_reset' => true],
    ['name' => 'wp-hot-journal-dir-fsync', 'type' => 'journal_unlink_dir_sync', 'source_token' => $sourceToken, 'generation' => 228, 'sync_order' => 3, 'receipt' => true, 'exclusive_lock' => false, 'journal_unlinked' => true],
    ['name' => 'wp-shm-lock-epoch', 'type' => 'shm_lock_epoch', 'source_token' => $sourceToken, 'generation' => 228, 'sync_order' => 4, 'receipt' => true, 'exclusive_lock' => true],
    ['name' => 'wp-import-savepoint-release', 'type' => 'savepoint_release', 'source_token' => $sourceToken, 'generation' => 228, 'sync_order' => 5, 'receipt' => true, 'exclusive_lock' => false, 'savepoint_released' => true],
];

$readers = [
    ['name' => 'wp-options-copy-reader', 'source_token' => $sourceToken, 'generation' => 228, 'reopened' => true, 'saw_durability_barrier' => true, 'pinned_old_source' => false],
    ['name' => 'wp-autoload-index-reader', 'source_token' => $sourceToken, 'generation' => 228, 'reopened' => true, 'saw_durability_barrier' => true, 'pinned_old_source' => false],
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next228AdmitDurableSource($publication, $barriers, $readers, $sourceToken);

$summary = [
    'scenario' => 'wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next228',
    'wordpressUse' => 'A copied WordPress plugin import reopens wp_options readers only after the checkpoint source has durable database, WAL reset, hot-journal unlink, SHM lock, and savepoint-release receipts.',
    'status' => $plan['status'],
    'currentSourceAdmitted' => $plan['current_source_admitted'],
    'writerAction' => $plan['next_writer_action'],
    'readerAction' => $plan['reader_action'],
    'admittedBarriers' => $plan['admitted_barrier_names'],
    'admittedReaders' => $plan['admitted_reader_names'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (($argv[1] ?? null) === '--self-test') {
    if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next228'
        || $summary['currentSourceAdmitted'] !== true
        || $summary['writerAction'] !== 'start_after_durable_checkpoint_source'
        || count($summary['admittedBarriers']) !== 5
        || count($summary['admittedReaders']) !== 2
    ) {
        fwrite(STDERR, "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next228 self-test failed\n");
        exit(1);
    }

    echo "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next228 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
