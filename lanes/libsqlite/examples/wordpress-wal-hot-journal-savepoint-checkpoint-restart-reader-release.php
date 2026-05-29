<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $digest('wp restart-reader-release checkpoint database');
$walDigest = $digest('wp restart-reader-release retained wal');
$writerDigest = $digest('wp restart-reader-release writer generation');

$passivePlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next212',
    'database_path' => '/srv/www/wp-content/database/wp-restart-reader-release.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-restart-reader-release.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-restart-reader-release.sqlite-wal',
    'page_size' => 512,
    'requested_checkpoint_frame' => 214,
    'checkpointed_frame' => 214,
    'busy' => false,
    'database_digest' => $databaseDigest,
    'wal_digest' => $walDigest,
    'writer_digest' => $writerDigest,
    'next_writer_generation' => 214,
    'operation_names' => ['verify_passive_checkpoint_reader_pin_current_source_next212'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next212'],
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::restartCheckpointAfterReaderRelease(
    $passivePlan,
    [
        [
            'name' => 'wp-options-admin-reader',
            'released' => true,
            'reader_end_frame' => 214,
            'reader_generation' => 214,
            'observed_database_digest' => $databaseDigest,
            'observed_wal_digest' => $walDigest,
            'observed_writer_digest' => $writerDigest,
        ],
        [
            'name' => 'wp-plugin-stale-reader',
            'released' => false,
            'reader_end_frame' => 212,
            'reader_generation' => 212,
            'observed_database_digest' => $databaseDigest,
            'observed_wal_digest' => $walDigest,
            'observed_writer_digest' => $writerDigest,
        ],
    ],
    [
        'wal_salt_before' => $digest('wp restart-reader-release salt before'),
        'wal_salt_after' => $digest('wp restart-reader-release salt after'),
        'hot_journal_digest' => $digest('wp restart-reader-release hot journal'),
        'savepoint_closed' => true,
        'exclusive_checkpoint_lock' => true,
        'database_synced' => true,
        'wal_header_synced' => true,
        'directory_synced' => true,
        'delete_hot_journal_after_reset' => true,
    ]
);

$summary = [
    'scenario' => 'wordpress-wal-hot-journal-savepoint-checkpoint-current-source-restart-reader-release',
    'wordpressUse' => 'A copied WordPress options import finishes hot-journal recovery and savepoint checkpointing, releases current readers, restarts the WAL with a rotated salt, and only then deletes the hot journal.',
    'status' => $plan['status'],
    'walAction' => $plan['wal_action'],
    'journalAction' => $plan['journal_action'],
    'syncSequence' => $plan['sync_sequence'],
    'reopenReaders' => $plan['reopen_reader_names'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-restart-reader-release'
        || $summary['walAction'] !== 'restart_wal_header_with_rotated_salt'
        || $summary['journalAction'] !== 'delete_hot_journal_after_wal_restart_sync'
        || $summary['reopenReaders'] !== ['wp-plugin-stale-reader']
    ) {
        fwrite(STDERR, "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-restart-reader-release self-test failed\n");
        exit(1);
    }

    echo "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-restart-reader-release self-test passed\n";
}

return $summary;
