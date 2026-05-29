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
$walPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 190, 0x19019001, 0x19019002);
$seed = SQLiteWal::checksumPair($walPrefix, false);
$walBytes = $walPrefix . pack('N*', $seed[0], $seed[1]);
$framePrefix = pack('N*', 2, 2, 0x19019001, 0x19019002);
$seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $page('committed autoload retry checkpoint'), false, $seed[0], $seed[1]);
$walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $page('committed autoload retry checkpoint');

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next190Plan(
    [
        'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next187',
        'database_path' => $databasePath,
        'wal_path' => $walPath,
        'retry_reader_token' => 'wp-options-retry-reader-token-next190',
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
    190
);

echo json_encode([
    'status' => $plan['status'],
    'can_publish_retry_checkpoint_source' => $plan['can_publish_retry_checkpoint_source'],
    'journal_present' => $plan['journal_present'],
    'wal_commit_frame_count' => $plan['wal_commit_frame_count'],
    'publication_token' => $plan['publication_token'],
    'dependency_closure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
