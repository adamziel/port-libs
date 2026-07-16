<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$generation = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next194',
    'database_path' => '/srv/www/wp-content/database/wp-options.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-options.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-options.sqlite-journal',
    'publication_token' => 'wp-options-retry-checkpoint-publication-next200',
    'previous_reader_epoch' => 196,
    'sealed_reader_epoch' => 200,
    'reader_ticket_ids' => ['wp-options-reader-next200-a', 'wp-options-reader-next200-b'],
    'reader_pages' => [1, 2],
    'can_expose_reopened_readers' => true,
    'dependencies' => [
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next194',
        'application-import-retry-checkpoint-reader-exposure',
    ],
];
$hotDigest = hash('sha256', 'wp-options recovered hot journal page set');
$checkpointDigest = hash('sha256', 'wp-options durable retry checkpoint image');

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next200AdmitDurableReaders(
    $generation,
    [
        [
            'ticket_id' => 'wp-options-reader-next200-a',
            'page_number' => 1,
            'reader_epoch' => 197,
            'publication_token' => $generation['publication_token'],
            'hot_journal_recovery_digest' => $hotDigest,
            'checkpoint_database_digest' => $checkpointDigest,
            'savepoint_generation' => 42,
            'wal_sync_receipt' => true,
            'database_sync_receipt' => true,
            'directory_sync_receipt' => true,
            'reader_reopened_after_hot_journal' => true,
            'savepoint_release_observed' => true,
        ],
        [
            'ticket_id' => 'wp-options-reader-next200-b',
            'page_number' => 2,
            'reader_epoch' => 200,
            'publication_token' => $generation['publication_token'],
            'hot_journal_recovery_digest' => $hotDigest,
            'checkpoint_database_digest' => $checkpointDigest,
            'savepoint_generation' => 42,
            'wal_sync_receipt' => true,
            'database_sync_receipt' => true,
            'directory_sync_receipt' => true,
            'reader_reopened_after_hot_journal' => true,
            'savepoint_release_observed' => true,
        ],
    ],
    $hotDigest,
    42,
    $checkpointDigest
);

$summary = [
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-current-source-next200',
    'applicationUse' => 'A copied Application option import admits retry-checkpoint readers only after hot-journal recovery, savepoint release, WAL/database sync, and directory sync receipts match the sealed reader generation.',
    'status' => $plan['status'],
    'canAdmitDurableReaders' => $plan['can_admit_durable_readers'],
    'receiptTickets' => $plan['receipt_ticket_ids'],
    'receiptPages' => $plan['receipt_pages'],
    'durabilityDigest' => $plan['durability_digest'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next200'
        || $summary['canAdmitDurableReaders'] !== true
        || $summary['receiptTickets'] !== ['wp-options-reader-next200-a', 'wp-options-reader-next200-b']
    ) {
        fwrite(STDERR, "application-wal-hot-journal-savepoint-checkpoint-current-source-next200 self-test failed\n");
        exit(1);
    }

    echo "application-wal-hot-journal-savepoint-checkpoint-current-source-next200 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
