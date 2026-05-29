<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalMvccCheckpointHotJournalCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next107.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$dirtyDatabase = $page('next107 dirty copied wp_options header')
    . $page('next107 dirty copied wp_options rows')
    . $page('next107 dirty copied active_plugins')
    . $page('next107 dirty copied autoload index');

$cleanPages = [
    1 => $page('next107 clean copied wp_options header'),
    2 => $page('next107 clean copied wp_options rows'),
    3 => $page('next107 clean copied active_plugins'),
    4 => $page('next107 clean copied autoload index'),
];
$nonce = 0x2026107;
$journalBytes = str_pad(SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($cleanPages), $nonce, 4, $sectorSize, $pageSize), $sectorSize, "\0");
foreach ($cleanPages as $pageNumber => $image) {
    $journalBytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
}
$journal = SQLiteRollbackJournal::parse($journalBytes, true);

$salt1 = 0x20260528;
$salt2 = 0x20260107;
$headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 107, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($headerPrefix, false);
$walBytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);
$append = static function (string $bytes, array &$seed, int $pageNumber, int $commitPageCount, string $label) use ($page, $salt1, $salt2): string {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};
$walBytes = $append($walBytes, $seed, 1, 0, 'next107 copied schema WAL draft');
$walBytes = $append($walBytes, $seed, 2, 4, 'next107 copied wp_options committed WAL row');
$walBytes = $append($walBytes, $seed, 3, 0, 'next107 copied active_plugins WAL draft');
$walBytes = $append($walBytes, $seed, 4, 4, 'next107 copied autoload committed WAL row');
$walBytes = $append($walBytes, $seed, 2, 0, 'next107 copied uncommitted option overwrite');

$plan = SQLiteWalMvccCheckpointHotJournalCurrentSourceNextPlan::plan(
    $journal,
    $dirtyDatabase,
    $journalBytes,
    $walBytes,
    $databasePath,
    [1, 2, 3, 4],
    $pageSize
);

$result = [
    'scenario' => 'wordpress-wal-mvcc-hotjournal-checkpoint-current-source-next107',
    'wordpressUse' => 'Preview a copied WordPress SQLite database where a crashed import left both a hot rollback journal and WAL sidecar: pinned current readers keep their committed WAL snapshot while the next reader sees hot-journal recovery followed by a committed WAL checkpoint.',
    'status' => $plan['status'],
    'hotRecovered' => $plan['hot_recovered'],
    'walStatus' => $plan['wal_status'],
    'dirtyReaderSources' => $plan['dirty_reader_sources'],
    'hotReaderSources' => $plan['hot_reader_sources'],
    'nextReaderSources' => $plan['next_reader_sources'],
    'dirtyReaderFrameIndexes' => $plan['dirty_reader_frame_indexes'],
    'hotReaderFrameIndexes' => $plan['hot_reader_frame_indexes'],
    'nextReaderUsesCheckpointDatabase' => $plan['next_reader_uses_checkpoint_database'],
    'hotToNextImagesMatch' => $plan['hot_to_next_images_match'],
    'discardedValidTailFrames' => $plan['discarded_valid_tail_frame_count'],
    'operationReasons' => $plan['operation_reasons'],
    'dependencies' => $plan['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    assert($result['status'] === 'wal-mvcc-hot-journal-checkpoint-current-source-next107');
    assert($result['hotRecovered'] === true);
    assert($result['walStatus'] === 'recovered_committed_prefix');
    assert($result['dirtyReaderSources'] === ['wal', 'wal', 'wal', 'wal']);
    assert($result['nextReaderSources'] === ['database', 'database', 'database', 'database']);
    assert($result['dirtyReaderFrameIndexes'] === [1, 2, 3, 4]);
    assert($result['hotReaderFrameIndexes'] === [1, 2, 3, 4]);
    assert($result['nextReaderUsesCheckpointDatabase'] === true);
    assert($result['hotToNextImagesMatch'] === true);
    assert($result['discardedValidTailFrames'] === 1);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
