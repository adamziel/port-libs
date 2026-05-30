<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteRollbackJournal.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalHeader.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalPage.php';
require_once __DIR__ . '/../src/SQLiteSavepointStack.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalCacheCurrentNextPlan.php';
require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointReplayPlan.php';

use PortLibs\LibSqlite\SQLitePagerMasterJournalCacheCurrentNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointReplayPlan;

$pageSize = 512;
$databasePath = '/wp-content/database/.ht.sqlite';
$masterPath = '/wp-content/database/wp-master-journal82';
$journalPath = $databasePath . '-journal';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$databaseBytes = $page('dirty schema after Application plugin crash')
    . $page('dirty active_plugins after Application plugin crash')
    . $page('dirty autoload index after Application plugin crash');

$nonce = 0x19820425;
$journalHeader = SQLiteRollbackJournalHeader::MAGIC . pack('N*', 3, $nonce, 3, 512, $pageSize);
$journalBytes = str_pad($journalHeader, 512, "\0");
foreach ([1 => $page('clean schema before plugin crash'), 2 => $page('clean active_plugins before plugin crash'), 3 => $page('clean autoload index before plugin crash')] as $pageNumber => $image) {
    $journalBytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
}
$journal = SQLiteRollbackJournal::parse($journalBytes, true);

$salt1 = 0x20260528;
$salt2 = 0x00000082;
$headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 82, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($headerPrefix, false);
$walBytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);
foreach ([[1, 0, 'wal schema retained'], [2, 3, 'wal active_plugins retained commit'], [3, 0, 'wal autoload draft after savepoint'], [2, 3, 'wal plugin commit discarded']] as $frame) {
    [$pageNumber, $commit, $label] = $frame;
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('application_import');
$savepoints->recordWalFrameWrite(1, 1);
$savepoints->recordWalFrameWrite(2, 2, true);
$savepoints->savepoint('plugin_batch');
$savepoints->recordWalFrameWrite(3, 3);
$savepoints->recordWalFrameWrite(4, 2, true);

$plan = SQLiteWalHotJournalSavepointReplayPlan::masterJournalCurrentSourceNext(
    $masterPath,
    $journalPath . "\n",
    null,
    $journal,
    $databaseBytes,
    $journalBytes,
    $savepoints,
    'plugin_batch',
    $wal,
    $walBytes,
    $databasePath,
    [1, 2, 3]
);

echo json_encode([
    'status' => $plan['status'],
    'reason' => $plan['reason'],
    'stale_current_member' => $plan['stale_current_member'],
    'next_master_member' => $plan['next_master_member'],
    'cache_action' => $plan['master_cache']['journal_rechecks'][$journalPath]['cache_action'],
    'hot_recovered' => $plan['replay']['hot_recovered'],
    'journal_action' => $plan['replay']['journal_action'],
    'retained_frame_count' => $plan['replay']['retained_frame_count'],
    'discarded_frame_count' => $plan['replay']['discarded_frame_count'],
    'current_reader_sources' => $plan['replay']['current_reader_sources'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
