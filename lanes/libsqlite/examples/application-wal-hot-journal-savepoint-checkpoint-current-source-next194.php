<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databasePath = '/srv/www/wp-content/database/wp-options.sqlite';
$walPath = $databasePath . '-wal';
$databaseBytes = $page('wp options checkpoint page one') . $page('wp options retry checkpoint page two');
$walPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 194, 0x19419401, 0x19419402);
$seed = SQLiteWal::checksumPair($walPrefix, false);
$walBytes = $walPrefix . pack('N*', $seed[0], $seed[1]);
$framePrefix = pack('N*', 2, 2, 0x19419401, 0x19419402);
$seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $page('committed autoload retry checkpoint'), false, $seed[0], $seed[1]);
$walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $page('committed autoload retry checkpoint');

$publication = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next190Plan(
    [
        'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next187',
        'database_path' => $databasePath,
        'wal_path' => $walPath,
        'retry_reader_token' => 'wp-options-retry-reader-token-next194',
        'next_wal_sha256' => hash('sha256', $walBytes),
        'can_admit_retry_checkpoint_source' => true,
        'requires_reader_reopen' => false,
        'post_apply_token_retired' => true,
        'hot_journal_observed' => true,
        'reader_page_numbers' => [1, 2],
        'reader_next_sources' => ['checkpoint-database', 'next-wal'],
        'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next187', 'directory_sync_verified'],
    ],
    [
        $databasePath => $databaseBytes,
        $walPath => $walBytes,
        $databasePath . '-journal' => null,
    ],
    $databaseBytes,
    $pageSize,
    194
);

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next194SealReaderGeneration(
    $publication,
    [
        [
            'ticket_id' => 'wp-options-reader-ticket-next194-a',
            'reader_epoch' => 195,
            'page_number' => 2,
            'source' => 'next-wal',
            'publication_token' => $publication['publication_token'],
            'database_sha256' => $publication['database_sha256'],
            'wal_sha256' => $publication['wal_sha256'],
            'checkpoint_sequence' => 194,
            'page_size' => $pageSize,
            'hot_journal_sha256' => null,
            'has_directory_sync_receipt' => true,
            'has_exclusive_checkpoint_lock_receipt' => true,
            'savepoint_closed' => true,
        ],
        [
            'ticket_id' => 'wp-options-reader-ticket-next194-b',
            'reader_epoch' => 196,
            'page_number' => 1,
            'source' => 'checkpoint-database',
            'publication_token' => $publication['publication_token'],
            'database_sha256' => $publication['database_sha256'],
            'wal_sha256' => $publication['wal_sha256'],
            'checkpoint_sequence' => 194,
            'page_size' => $pageSize,
            'hot_journal_sha256' => null,
            'has_directory_sync_receipt' => true,
            'has_exclusive_checkpoint_lock_receipt' => true,
            'savepoint_closed' => true,
        ],
    ],
    190
);

echo json_encode([
    'status' => $plan['status'],
    'can_expose_reopened_readers' => $plan['can_expose_reopened_readers'],
    'reader_ticket_count' => $plan['reader_ticket_count'],
    'sealed_reader_epoch' => $plan['sealed_reader_epoch'],
    'reader_sources' => $plan['reader_sources'],
    'seal_digest' => $plan['seal_digest'],
    'dependency_closure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
