<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$writerPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next258',
    'post_restart_writer_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/application.sqlite',
    'wal_path' => '/srv/www/wp-content/database/application.sqlite-wal',
    'source_token' => 'application-next261-source',
    'commit_generation' => 261,
    'checkpoint_frame' => 0,
    'database_digest' => hash('sha256', 'application next261 published database'),
    'page_cache_digest' => hash('sha256', 'application next261 clean cache'),
    'restart_wal_salt' => ['00000105', '00000206'],
    'accepted_writer_receipt_names' => ['first-frame', 'header-salt', 'reader-fence', 'sync'],
    'reopened_reader_names' => ['front-page', 'plugin-cache', 'wp-options-import'],
    'readmark_slots' => [1, 2, 4],
    'operation_names' => ['admit_post_restart_writer_current_source_next258'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next258'],
];

$base = [
    'database_path' => $writerPlan['database_path'],
    'wal_path' => $writerPlan['wal_path'],
    'source_token' => $writerPlan['source_token'],
    'publish_generation' => 262,
    'checkpoint_frame' => 0,
    'database_digest' => $writerPlan['database_digest'],
    'page_cache_digest' => $writerPlan['page_cache_digest'],
    'published_wal_salt' => ['00000a0b', '00000c0d'],
    'writer_receipt_names' => $writerPlan['accepted_writer_receipt_names'],
    'readmark_slots' => $writerPlan['readmark_slots'],
    'reader_names' => $writerPlan['reopened_reader_names'],
    'mx_frame' => 1,
    'savepoint_depth' => 0,
    'exclusive_lock_held' => true,
    'hot_journal_visible' => false,
    'database_synced' => true,
    'wal_synced' => true,
    'shm_synced' => true,
    'io_error' => null,
];

$receipts = [
    array_replace($base, ['name' => 'application-next261-database-image', 'receipt_type' => 'database-image']),
    array_replace($base, ['name' => 'application-next261-wal-frame', 'receipt_type' => 'wal-frame']),
    array_replace($base, ['name' => 'application-next261-shm-index', 'receipt_type' => 'shm-index']),
    array_replace($base, ['name' => 'application-next261-readmark-fence', 'receipt_type' => 'readmark-fence']),
    array_replace($base, ['name' => 'application-next261-savepoint-release', 'receipt_type' => 'savepoint-release']),
    array_replace($base, ['name' => 'application-next261-sync', 'receipt_type' => 'sync']),
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next261SealPublishedCurrentSource($writerPlan, $receipts);
$summary = [
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-current-source-next261',
    'applicationUse' => 'A copied Application import publishes the first post-restart WAL writer as the current source only after database, WAL, SHM, read-mark, savepoint, and sync receipts all match the recovered source.',
    'status' => $plan['status'],
    'writerAction' => $plan['writer_action'],
    'walAction' => $plan['wal_action'],
    'readerAction' => $plan['reader_action'],
    'syncAction' => $plan['sync_action'],
    'blockedReasons' => $plan['blocked_publish_reasons'],
    'publicationDigest' => $plan['publication_digest'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next261'
        || $summary['writerAction'] !== 'publish_post_restart_writer_generation_262'
        || $summary['blockedReasons'] !== []
    ) {
        fwrite(STDERR, "application-wal-hot-journal-savepoint-checkpoint-current-source-next261 self-test failed\n");
        exit(1);
    }
    echo "application-wal-hot-journal-savepoint-checkpoint-current-source-next261 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
