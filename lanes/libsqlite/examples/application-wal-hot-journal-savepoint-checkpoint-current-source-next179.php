<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next179.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp next179 checkpointed options') . $page('wp next179 checkpointed active_plugins');
$walBytes = $page('wp next179 retry wal frame');
$receiptDigest = hash('sha256', 'wp-next179-receipt');
$receipt = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next178',
    'database_path' => $databasePath,
    'journal_path' => $databasePath . '-journal',
    'wal_path' => $databasePath . '-wal',
    'can_publish_receipt' => true,
    'blocked_reasons' => [],
    'database_sha256' => hash('sha256', $databaseBytes),
    'wal_sha256' => hash('sha256', $walBytes),
    'receipt_digest' => $receiptDigest,
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next178'],
];
$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::planReopenReadsAfterReceipt(
    $receipt,
    [
        ['page' => 1, 'source' => 'database', 'receipt_digest' => $receiptDigest, 'expected_image' => $page('wp next179 checkpointed options'), 'actual_image' => $page('wp next179 checkpointed options')],
        ['page' => 2, 'source' => 'wal', 'receipt_digest' => $receiptDigest, 'expected_image' => $page('wp next179 retry wal frame'), 'actual_image' => $page('wp next179 retry wal frame')],
    ],
    $databaseBytes,
    null,
    $walBytes
);

echo json_encode([
    'status' => $plan['status'],
    'can_reopen_after_checkpoint' => $plan['can_reopen_after_checkpoint'],
    'read_sources' => $plan['read_sources'],
    'blocked_reasons' => $plan['blocked_reasons'],
    'receipt_digest' => $plan['receipt_digest'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
