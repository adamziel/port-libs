<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$databaseBytes = $page('base copied wp_options schema')
    . $page('base copied wp_options rows')
    . $page('base copied wp_options autoload index');

$salt1 = 0x35353535;
$salt2 = 0x75757575;
$headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 35, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($headerPrefix, false);
$walBytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);
$appendFrame = static function (string $bytes, array &$seed, int $pageNumber, int $commitPageCount, string $image) use ($salt1, $salt2): string {
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};
$walBytes = $appendFrame($walBytes, $seed, 2, 0, $page('frame 1 retained wp_options import draft'));
$walBytes = $appendFrame($walBytes, $seed, 3, 3, $page('frame 2 retained autoload index commit'));
$walBytes = $appendFrame($walBytes, $seed, 2, 0, $page('frame 3 plugin savepoint draft'));
$walBytes = $appendFrame($walBytes, $seed, 2, 3, $page('frame 4 plugin savepoint commit'));
$walBytes = $appendFrame($walBytes, $seed, 3, 0, $page('frame 5 nested transient draft'));
$wal = SQLiteWal::parse($walBytes, null, true);

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wp-import');
$savepoints->recordPageImageWrite(2, $page('base copied wp_options rows'));
$savepoints->recordWalFrameWrite(1, 2);
$savepoints->recordPageImageWrite(3, $page('base copied wp_options autoload index'));
$savepoints->recordWalFrameWrite(2, 3, true);
$savepoints->savepoint('plugin-settings');
$savepoints->recordWalFrameWrite(3, 2);
$savepoints->recordWalFrameWrite(4, 2, true);
$savepoints->savepoint('nested-transient');
$savepoints->recordWalFrameWrite(5, 3);

$plan = SQLiteWalSavepointCheckpointPlan::readerPinCurrentNextAfterRollbackTo(
    $savepoints,
    'plugin-settings',
    $wal,
    $walBytes,
    $databaseBytes,
    [1, 2, 3],
    [2, 4, null],
    'restart'
);

$report = [
    'scenario' => 'application-wal-savepoint-reader-pin-current-next35',
    'applicationUse' => 'During a copied wp_options import, roll back a plugin-settings savepoint while an existing WAL reader keeps its read-mark pin, then report the current reader snapshot and the next reader view after the checkpoint/reset attempt without requiring ext/sqlite.',
    'status' => $plan['status'],
    'checkpointReason' => $plan['checkpoint_reason'],
    'retainedFrames' => $plan['retained_frame_count'],
    'discardedFrames' => $plan['discarded_frame_count'],
    'currentReaderEndFrame' => $plan['current_reader_end_frame'],
    'nextReaderEndFrame' => $plan['next_reader_end_frame'],
    'currentReaderSources' => $plan['current_reader_sources'],
    'nextReaderSources' => $plan['next_reader_sources'],
    'currentReadmarkPinnedFrame' => $plan['current_read_marks']['checkpoint_pinned_frame'],
    'nextReadmarkCanFinish' => $plan['next_read_marks']['checkpoint_can_finish'],
    'nextUsesPreservedWal' => $plan['next_reader_uses_preserved_wal'],
    'dependencies' => $plan['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    assert($report['status'] === 'busy');
    assert($report['checkpointReason'] === 'reader_blocks_wal_reset');
    assert($report['retainedFrames'] === 2);
    assert($report['discardedFrames'] === 3);
    assert($report['currentReadmarkPinnedFrame'] === 2);
    assert($report['nextReadmarkCanFinish'] === true);
    assert($report['nextUsesPreservedWal'] === true);
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
