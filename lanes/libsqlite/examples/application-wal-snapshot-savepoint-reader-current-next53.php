<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('base copied wp_options schema')
    . $page('base copied wp_options rows')
    . $page('base copied wp_options autoload index');

$salt1 = 0x53535353;
$salt2 = 0x53535354;
$headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 53, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($headerPrefix, false);
$walBytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);
$appendFrame = static function (string $bytes, array &$seed, int $pageNumber, int $commitPageCount, string $image) use ($salt1, $salt2): string {
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};
$walBytes = $appendFrame($walBytes, $seed, 1, 0, $page('frame1 retained wp_options schema'));
$walBytes = $appendFrame($walBytes, $seed, 2, 3, $page('frame2 reader pinned options'));
$walBytes = $appendFrame($walBytes, $seed, 2, 0, $page('frame3 released plugin draft'));
$walBytes = $appendFrame($walBytes, $seed, 3, 3, $page('frame4 released autoload index'));
$walBytes = $appendFrame($walBytes, $seed, 2, 3, $page('frame5 released nested transient'));
$wal = SQLiteWal::parse($walBytes, null, true);

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wp-import');
$savepoints->recordWalFrameWrite(1, 1);
$savepoints->recordWalFrameWrite(2, 2, true);
$savepoints->savepoint('plugin-settings');
$savepoints->recordWalFrameWrite(3, 2);
$savepoints->recordWalFrameWrite(4, 3, true);
$savepoints->savepoint('nested-transient');
$savepoints->recordWalFrameWrite(5, 2, true);

$plan = SQLiteWalSavepointCheckpointPlan::readerCurrentNextAfterRelease(
    $savepoints,
    'plugin-settings',
    $wal,
    $databaseBytes,
    [1, 2, 3],
    [2, null],
    'restart'
);

$report = [
    'scenario' => 'application-wal-snapshot-savepoint-reader-current-next53',
    'applicationUse' => 'During a copied wp_options import, RELEASE merges plugin savepoint WAL frames while an existing reader keeps its earlier read-mark snapshot and the next reader sees the released savepoint frames without ext/sqlite.',
    'status' => $plan['status'],
    'checkpointReason' => $plan['checkpoint_reason'],
    'releasedFrameNames' => $plan['released_frame_names'],
    'mergedPageNumbers' => $plan['merged_page_numbers'],
    'currentReaderEndFrame' => $plan['current_reader_end_frame'],
    'nextReaderEndFrame' => $plan['next_reader_end_frame'],
    'currentReaderSources' => $plan['current_reader_sources'],
    'nextReaderSources' => $plan['next_reader_sources'],
    'currentReaderFrames' => $plan['current_reader_frame_indexes'],
    'nextReaderFrames' => $plan['next_reader_frame_indexes'],
    'nextUsesPreservedWal' => $plan['next_reader_uses_preserved_wal'],
    'dependencies' => $plan['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    assert($report['status'] === 'busy');
    assert($report['checkpointReason'] === 'reader_blocks_checkpoint_completion');
    assert($report['releasedFrameNames'] === ['plugin-settings', 'nested-transient']);
    assert($report['mergedPageNumbers'] === [2, 3]);
    assert($report['currentReaderFrames'] === [1, 2, null]);
    assert($report['nextReaderFrames'] === [1, 5, 4]);
    assert($report['nextUsesPreservedWal'] === true);
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
