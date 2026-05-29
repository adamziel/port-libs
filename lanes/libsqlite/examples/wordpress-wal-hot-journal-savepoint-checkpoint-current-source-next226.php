<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$databasePath = '/srv/www/wp-content/database/wordpress.sqlite';
$walPath = $databasePath . '-wal';
$journalPath = $databasePath . '-journal';
$databaseBytes = str_repeat('checkpointed wp_options import database page ', 16);
$walBytes = 'restart wal header after checkpoint reset';
$databaseDigest = hash('sha256', $databaseBytes);
$walDigest = hash('sha256', $walBytes);

$reset = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next218',
    'mode' => 'restart',
    'database_path' => $databasePath,
    'wal_path' => $walPath,
    'journal_path' => $journalPath,
    'can_reset_wal' => true,
    'next_writer_generation' => 227,
    'operation_names' => ['publish_wal_reset_current_source_next218'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next218'],
];
$files = [
    $databasePath => $databaseBytes,
    $walPath => $walBytes,
];
$receipts = [
    [
        'name' => 'wp-options-import-writer',
        'mode' => 'restart',
        'writer_generation' => 227,
        'database_digest' => $databaseDigest,
        'wal_digest' => $walDigest,
        'database_synced' => true,
        'wal_synced' => true,
        'directory_synced' => true,
        'hot_journal_absent' => true,
        'savepoint_closed' => true,
        'readers_reopened' => true,
    ],
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next226VerifyResetFiles($reset, $files, $receipts, $walBytes, $databaseDigest);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next226');
    assert($plan['can_publish_reopened_current_source'] === true);
    assert($plan['journal_present'] === false);
    assert(in_array('wordpress-import-wal-reset-file-state-reopen', $plan['dependencies'], true));
    echo "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next226 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next226',
    'status' => $plan['status'],
    'canPublishReopenedCurrentSource' => $plan['can_publish_reopened_current_source'],
    'receiptCount' => $plan['receipt_count'],
    'wordpressUse' => 'A copied wp_options import can reopen readers after hot-journal recovery and savepoint checkpoint reset only when the database, WAL reset bytes, missing hot journal, and sync receipts agree.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
