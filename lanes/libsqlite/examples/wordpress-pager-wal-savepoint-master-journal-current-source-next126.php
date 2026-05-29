<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerWalSavepointMasterJournalCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/wp/wp-content/database/wp-options.sqlite';
$journalPath = $databasePath . '-journal';
$masterPath = '/srv/wp/wp-content/database/wp-import-master-journal';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$databaseBytes = $page('wp next126 dirty header') . $page('wp next126 dirty active_plugins') . $page('wp next126 dirty autoload');
$cleanPages = [
    1 => $page('wp next126 clean header from master journal'),
    2 => $page('wp next126 clean active_plugins from master journal'),
    3 => $page('wp next126 clean autoload from master journal'),
];

$nonce = 0x126126;
$journalBytes = str_pad(
    SQLiteRollbackJournalHeader::MAGIC . pack('N*', 3, $nonce, 3, $sectorSize, $pageSize),
    $sectorSize,
    "\0"
);
foreach ($cleanPages as $pageNumber => $image) {
    $journalBytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
}
$journal = SQLiteRollbackJournal::parse($journalBytes, true);

$salt1 = 0x20260528;
$salt2 = 0x00000126;
$headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 126, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($headerPrefix, false);
$walBytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [1, 0, 'wp next126 wal retained schema'],
    [2, 3, 'wp next126 wal retained active_plugins'],
    [3, 0, 'wp next126 wal draft autoload'],
    [2, 3, 'wp next126 wal discarded plugin activation'],
] as $frame) {
    [$pageNumber, $commitPageCount, $label] = $frame;
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp_plugin_import');
$stack->recordWalFrameWrite(1, 1);
$stack->recordWalFrameWrite(2, 2, true);
$stack->savepoint('plugin_batch');
$stack->recordWalFrameWrite(3, 3);
$stack->recordWalFrameWrite(4, 2, true);

$plan = SQLitePagerWalSavepointMasterJournalCurrentSourceNextPlan::plan(
    $masterPath,
    "/srv/wp/wp-content/database/stale.sqlite-journal\n",
    $journalPath . "\n",
    $journalPath . "\n",
    $journal,
    $databaseBytes,
    $journalBytes,
    $stack,
    'plugin_batch',
    $wal,
    $walBytes,
    $databasePath,
    [1, 2, 3]
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'pager_wal_savepoint_master_journal_current_source_next126');
    assert($plan['cached_stale_rejected'] === true);
    assert($plan['current_hot_recovered'] === true);
    assert(str_contains((string) $plan['checkpoint_database_bytes'], 'wp next126 wal retained active_plugins'));
    assert(!str_contains((string) $plan['checkpoint_database_bytes'], 'wp next126 wal discarded plugin activation'));
    echo "wordpress-pager-wal-savepoint-master-journal-current-source-next126 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'cached_stale_rejected' => $plan['cached_stale_rejected'],
    'current_hot_recovered' => $plan['current_hot_recovered'],
    'retained_frame_count' => $plan['retained_frame_count'],
    'discarded_frame_count' => $plan['discarded_frame_count'],
], JSON_PRETTY_PRINT) . "\n";
