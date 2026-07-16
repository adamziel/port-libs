<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalCheckpointReaderCurrentSourceNextPlan;

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-options-next120.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$cleanPages = [
    1 => $page('wp next120 clean sqlite header'),
    2 => $page('wp next120 clean wp_options root'),
    3 => $page('wp next120 clean plugin settings'),
    4 => $page('wp next120 clean autoload index'),
];
$dirtyDatabase = $page('wp next120 dirty sqlite header')
    . $page('wp next120 dirty wp_options root')
    . $page('wp next120 dirty plugin settings')
    . $page('wp next120 dirty autoload index');

$nonce = 0x2026120;
$journalBytes = str_pad(
    SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($cleanPages), $nonce, count($cleanPages), $sectorSize, $pageSize),
    $sectorSize,
    "\0"
);
foreach ($cleanPages as $pageNumber => $pageImage) {
    $journalBytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
}
$journal = SQLiteRollbackJournal::parse($journalBytes, true);

$salt1 = 0x20260528;
$salt2 = 0x20261200;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 120, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [1, 0, 'wp next120 wal schema retained draft'],
    [2, 4, 'wp next120 wal options retained commit'],
    [3, 0, 'wp next120 wal plugin stale draft'],
    [4, 4, 'wp next120 wal autoload stale commit'],
    [2, 4, 'wp next120 wal options stale reader tail'],
] as [$pageNumber, $commit, $label]) {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wp-options-import-next120');
$savepoints->recordWalFrameWrite(1, 1);
$savepoints->recordWalFrameWrite(2, 2, true);
$savepoints->savepoint('plugin-settings-next120');
$savepoints->recordWalFrameWrite(3, 3);
$savepoints->recordWalFrameWrite(4, 4, true);
$savepoints->recordWalFrameWrite(5, 2, true);

$plan = SQLiteWalHotJournalCheckpointReaderCurrentSourceNextPlan::hotJournalCheckpointReaderPinnedSourcePlan(
    $journal,
    $dirtyDatabase,
    $journalBytes,
    $savepoints,
    'plugin-settings-next120',
    $wal,
    $walBytes,
    $walBytes,
    $databasePath,
    [1, 2, 3, 4],
    'restart',
    5,
    $pageSize
);

$summary = [
    'scenario' => 'application-wal-hot-journal-checkpoint-reader-current-source-next120',
    'applicationUse' => 'Recover a copied wp_options hot rollback journal, roll back a failed savepoint WAL tail, and prove a pinned reader can keep its stale WAL source while checkpoint reset uses the retained current prefix after the reader releases.',
    'status' => $plan['status'],
    'readerSourceMatchesCurrent' => $plan['reader_source_matches_current'],
    'pinnedCheckpointBusy' => $plan['pinned_checkpoint_busy'],
    'releasedCheckpointBusy' => $plan['released_checkpoint_busy'],
    'pinnedWalAction' => $plan['pinned_wal_action'],
    'releasedWalAction' => $plan['released_wal_action'],
    'readerSources' => $plan['reader_sources'],
    'currentSources' => $plan['current_sources'],
    'releasedSources' => $plan['released_next_sources'],
    'dependencyClosure' => 'no new support component needed; this composes existing native PHP rollback-journal recovery, WAL savepoint truncation, and checkpoint reader-source primitives',
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    assert($summary['status'] === 'hot-journal-checkpoint-reader-current-source-next120');
    assert($summary['readerSourceMatchesCurrent'] === false);
    assert($summary['pinnedCheckpointBusy'] === true);
    assert($summary['releasedCheckpointBusy'] === false);
    assert($summary['releasedWalAction'] === 'restart_wal');
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

return $summary;
