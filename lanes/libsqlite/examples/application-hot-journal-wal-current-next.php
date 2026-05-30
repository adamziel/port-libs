<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteRollbackJournalHeader.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalPage.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournal.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLitePagerHotJournalWalRecoveryPlan.php';

use PortLibs\LibSqlite\SQLitePagerHotJournalWalRecoveryPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$cleanHeader = $page('clean wp_options header before interrupted import');
$cleanOptions = $page('clean wp_options page before interrupted import');
$dirtyDatabase = $page('dirty wp_options header after interrupted import')
    . $page('dirty wp_options page after interrupted import')
    . $page('dirty transient page after interrupted import');

$nonce = 0x24681357;
$journalBytes = str_pad(
    SQLiteRollbackJournalHeader::MAGIC . pack('N*', 2, $nonce, 2, $sectorSize, $pageSize),
    $sectorSize,
    "\0"
);
foreach ([1 => $cleanHeader, 2 => $cleanOptions] as $pageNumber => $image) {
    $journalBytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
}
$journal = SQLiteRollbackJournal::parse($journalBytes, true);

$salt1 = 0x12345678;
$salt2 = 0x87654321;
$walHeader = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 41, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($walHeader, false);
$walBytes = $walHeader . pack('N*', $seed[0], $seed[1]);
foreach ([
    [2, 2, $page('wal committed active_plugins after journal recovery')],
    [3, 0, $page('wal uncommitted transient draft after crash')],
] as [$pageNumber, $commitPageCount, $image]) {
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$visibility = SQLitePagerHotJournalWalRecoveryPlan::currentNextVisibility(
    $journal,
    $dirtyDatabase,
    $journalBytes,
    $walBytes,
    $databasePath,
    [1, 2, 3],
    $pageSize
);

echo json_encode([
    'applicationUse' => 'Compare copied wp_options reader-visible pages before and after hot rollback-journal recovery followed by committed WAL prefix recovery, showing the next reader sees journal-restored pages plus committed WAL pages while uncommitted WAL tail pages stay invisible without ext/sqlite.',
    'status' => $visibility['status'],
    'currentSources' => $visibility['current_reader_sources'],
    'nextSources' => $visibility['next_reader_sources'],
    'nextUsesHotJournalDatabase' => $visibility['next_uses_hot_journal_database'],
    'nextUsesWalCheckpointDatabase' => $visibility['next_uses_wal_checkpoint_database'],
    'discardedValidTailFrames' => $visibility['discarded_valid_tail_frame_count'],
    'currentPageOneLabel' => rtrim(substr((string) $visibility['current_reader'][0]['image'], 0, 48), '.'),
    'nextPageOneLabel' => rtrim(substr((string) $visibility['next_reader'][0]['image'], 0, 48), '.'),
    'nextPageTwoLabel' => rtrim(substr((string) $visibility['next_reader'][1]['image'], 0, 48), '.'),
    'nextPageThreeError' => $visibility['next_reader'][2]['error'] ?? null,
], JSON_PRETTY_PRINT) . PHP_EOL;
