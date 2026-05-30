<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $digest('application reader-drain copied database after hot journal checkpoint');
$walDigest = $digest('application reader-drain retained wal before reader drain');
$writerDigest = $digest('application reader-drain post checkpoint writer fence');

$passivePlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next212',
    'database_path' => '/srv/www/wp-content/database/wp.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp.sqlite-wal',
    'page_size' => 4096,
    'requested_checkpoint_frame' => 216,
    'checkpointed_frame' => 214,
    'busy' => true,
    'database_digest' => $databaseDigest,
    'wal_digest' => $walDigest,
    'writer_digest' => $writerDigest,
    'next_writer_generation' => 216,
    'minimum_statement_generation' => 213,
    'active_reader_names' => ['wp-options-import-reader', 'wp-cron-reader'],
    'reopen_reader_names' => ['stale-plugin-settings-reader'],
    'operation_names' => ['verify_passive_checkpoint_reader_pin_current_source_next212'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next212'],
];

$readerTransitions = [
    [
        'name' => 'wp-options-import-reader',
        'released' => true,
        'closed' => true,
        'reader_generation' => 216,
        'reader_end_frame' => 214,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
    ],
    [
        'name' => 'wp-cron-reader',
        'released' => true,
        'closed' => true,
        'reader_generation' => 216,
        'reader_end_frame' => 216,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
    ],
    [
        'name' => 'stale-plugin-settings-reader',
        'reopened' => true,
        'closed' => true,
        'reader_generation' => 216,
        'reader_end_frame' => 213,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
    ],
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::restartOrTruncateCheckpointAfterReaderDrain(
    $passivePlan,
    $readerTransitions,
    'TRUNCATE'
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-reader-drain');
    assert($plan['truncate_allowed'] === true);
    assert($plan['wal_action'] === 'truncate_wal_after_reader_drain');
    assert($plan['released_active_reader_names'] === ['wp-options-import-reader', 'wp-cron-reader']);
    assert($plan['reopened_stale_reader_names'] === ['stale-plugin-settings-reader']);
    echo "application-wal-hot-journal-savepoint-checkpoint-current-source-reader-drain self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'database' => $plan['database_path'],
    'wal' => $plan['wal_path'],
    'mode' => $plan['mode'],
    'checkpointed_frame' => $plan['checkpointed_frame'],
    'wal_action' => $plan['wal_action'],
    'released_readers' => $plan['released_active_reader_names'],
    'reopened_readers' => $plan['reopened_stale_reader_names'],
    'dependency_closure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
