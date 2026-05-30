<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $digest('application next244 checkpointed database bytes');
$pageCacheDigest = $digest('application next244 clean page cache');
$baseline = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next240',
    'autocheckpoint_baseline_allowed' => true,
    'database_path' => '/srv/www/wp-content/database/application.sqlite',
    'journal_path' => '/srv/www/wp-content/database/application.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/application.sqlite-wal',
    'source_token' => 'application-current-source-next244',
    'schema_cookie' => 2440,
    'commit_generation' => 2441,
    'database_digest' => $databaseDigest,
    'page_cache_digest' => $pageCacheDigest,
    'wal_index_salt' => ['wp-next244-salt-a', 'wp-next244-salt-b'],
    'wal_index_mx_frame' => 21,
    'checkpoint_frame' => 18,
    'dirty_pages' => [1, 3, 7],
    'commit_frames' => [19, 20, 21],
    'operation_names' => ['admit_autocheckpoint_baseline_next240'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next240'],
];

$durableReceipts = [
    [
        'name' => 'wp-options-checkpoint-sync',
        'source_token' => $baseline['source_token'],
        'schema_cookie' => $baseline['schema_cookie'],
        'commit_generation' => $baseline['commit_generation'],
        'database_digest' => $databaseDigest,
        'page_cache_digest' => $pageCacheDigest,
        'wal_index_salt' => $baseline['wal_index_salt'],
        'wal_index_mx_frame' => $baseline['wal_index_mx_frame'],
        'checkpoint_frame' => $baseline['checkpoint_frame'],
        'database_pages_written' => [1, 3],
        'wal_frames_synced' => [19, 20],
        'exclusive_lock_held' => true,
        'database_sync_done' => true,
        'wal_sync_done' => true,
        'directory_sync_done' => true,
        'hot_journal_deleted' => true,
        'stale_wal_preserved' => false,
    ],
    [
        'name' => 'wp-usermeta-checkpoint-sync',
        'source_token' => $baseline['source_token'],
        'schema_cookie' => $baseline['schema_cookie'],
        'commit_generation' => $baseline['commit_generation'],
        'database_digest' => $databaseDigest,
        'page_cache_digest' => $pageCacheDigest,
        'wal_index_salt' => $baseline['wal_index_salt'],
        'wal_index_mx_frame' => $baseline['wal_index_mx_frame'],
        'checkpoint_frame' => $baseline['checkpoint_frame'],
        'database_pages_written' => [7],
        'wal_frames_synced' => [21],
        'exclusive_lock_held' => true,
        'database_sync_done' => true,
        'wal_sync_done' => true,
        'directory_sync_done' => true,
        'hot_journal_deleted' => true,
        'stale_wal_preserved' => false,
    ],
];
$readerAcks = [];
foreach (['wp-options-reader', 'wp-usermeta-reader'] as $name) {
    $readerAcks[] = [
        'name' => $name,
        'source_token' => $baseline['source_token'],
        'schema_cookie' => $baseline['schema_cookie'],
        'commit_generation' => $baseline['commit_generation'],
        'database_digest' => $databaseDigest,
        'wal_index_salt' => $baseline['wal_index_salt'],
        'wal_index_mx_frame' => $baseline['wal_index_mx_frame'],
        'checkpoint_frame' => $baseline['checkpoint_frame'],
        'reader_generation' => $baseline['commit_generation'],
        'snapshot_reopened' => true,
        'readmark_cleared' => true,
        'hot_journal_seen' => false,
        'stale_wal_seen' => false,
    ];
}

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next244SealDurableCurrentSource($baseline, $durableReceipts, $readerAcks);
$summary = [
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-current-source-next244',
    'status' => $plan['status'],
    'applicationUse' => 'A copied Application import retires a recovered hot journal and stale WAL sidecar only after checkpoint bytes, fsync receipts, and reopened wp_options/usermeta readers all match the current source.',
    'hotJournalAction' => $plan['hot_journal_action'],
    'walSidecarAction' => $plan['wal_sidecar_action'],
    'readerAction' => $plan['reader_action'],
    'durablyWrittenPages' => $plan['durably_written_pages'],
    'durablySyncedFrames' => $plan['durably_synced_frames'],
    'sealedCurrentSource' => $plan['sealed_current_source'],
    'sealDigest' => $plan['seal_digest'],
];

if (($argv[1] ?? null) === '--self-test') {
    if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next244'
        || $summary['sealedCurrentSource'] !== true
        || $summary['durablyWrittenPages'] !== [1, 3, 7]
        || $summary['durablySyncedFrames'] !== [19, 20, 21]
    ) {
        fwrite(STDERR, "application-wal-hot-journal-savepoint-checkpoint-current-source-next244 self-test failed\n");
        exit(1);
    }
    echo "application-wal-hot-journal-savepoint-checkpoint-current-source-next244 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
