<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$digest = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $digest('application next218 checkpointed wp_options database');
$walDigest = $digest('application next218 wal before truncate');
$writerDigest = $digest('application next218 writer fence');

$checkpoint = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next212',
    'database_path' => '/srv/www/wp-content/database/application.sqlite',
    'journal_path' => '/srv/www/wp-content/database/application.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/application.sqlite-wal',
    'page_size' => 4096,
    'requested_checkpoint_frame' => 218,
    'checkpointed_frame' => 218,
    'busy' => false,
    'database_digest' => $databaseDigest,
    'wal_digest' => $walDigest,
    'writer_digest' => $writerDigest,
    'next_writer_generation' => 219,
    'minimum_statement_generation' => 200,
    'active_reader_names' => [],
    'reopen_reader_names' => [],
    'operation_names' => ['verify_passive_checkpoint_reader_pin_current_source_next212'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next212'],
];

$writers = [
    [
        'name' => 'wp-options-import-writer',
        'writer_generation' => 219,
        'start_frame' => 200,
        'last_frame' => 218,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
        'sync_receipt' => true,
    ],
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next218RestartOrTruncate($checkpoint, $writers, 'truncate');

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next218');
    assert($plan['truncate_allowed'] === true);
    assert($plan['wal_action'] === 'truncate_wal_to_zero_bytes');
    assert(in_array('application-import-checkpoint-reset-waits-for-current-source-reopen', $plan['dependencies'], true));
    echo "application-wal-hot-journal-savepoint-checkpoint-current-source-next218 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-current-source-next218',
    'status' => $plan['status'],
    'walAction' => $plan['wal_action'],
    'truncateAllowed' => $plan['truncate_allowed'],
    'applicationUse' => 'A copied wp_options import can publish a TRUNCATE checkpoint only after hot-journal recovery, savepoint release, current-source reader reopen, and writer sync receipts agree on the checkpointed WAL generation.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
