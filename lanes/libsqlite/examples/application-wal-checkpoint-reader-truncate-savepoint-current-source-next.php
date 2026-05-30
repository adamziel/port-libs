<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalCheckpointReaderTruncateSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp schema base before plugin import')
    . $page('wp siteurl base before plugin import')
    . $page('wp autoload base before plugin import')
    . $page('wp transient base before plugin import')
    . $page('wp active_plugins base before plugin import');
$makeWal = static function (array $frames) use ($pageSize, $page): string {
    $salt1 = 0x12812801;
    $salt2 = 0x12812802;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 128, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as [$pageNumber, $commitPageCount, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};
$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp-import-next128');
$stack->recordWalFrameWrite(1, 1);
$stack->recordWalFrameWrite(2, 2, true);
$stack->savepoint('plugin-settings-next128');
$stack->recordWalFrameWrite(3, 3);
$stack->recordWalFrameWrite(4, 4, true);
$stack->recordWalFrameWrite(5, 2, true);
$stack->recordWalFrameWrite(6, 5, true);

$walBytes = $makeWal([
    [1, 0, 'wp retained schema version in WAL'],
    [2, 5, 'wp retained siteurl commit in WAL'],
    [3, 0, 'wp discarded autoload draft in savepoint'],
    [4, 5, 'wp discarded transient cleanup in savepoint'],
    [2, 5, 'wp discarded siteurl retry tail in savepoint'],
    [5, 5, 'wp discarded active_plugins tail in savepoint'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$plan = SQLiteWalCheckpointReaderTruncateSavepointCurrentSourceNextPlan::plan(
    $stack,
    'plugin-settings-next128',
    $wal,
    $walBytes,
    $databaseBytes,
    [1, 2, 3, 4, 5],
    2
);

$summary = [
    'scenario' => 'application-wal-checkpoint-reader-truncate-savepoint-current-source-next128',
    'applicationUse' => 'A copied Application SQLite database rolls a plugin import back to a WAL savepoint while a reader still pins the retained current WAL prefix; truncate checkpoint must preserve that source until the reader releases.',
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'readerEndFrame' => $plan['reader_end_frame'],
    'retainedFrameCount' => $plan['retained_frame_count'],
    'discardedFrameIndexes' => $plan['discarded_frame_indexes'],
    'pinnedCheckpointBusy' => $plan['pinned_checkpoint_busy'],
    'pinnedWalAction' => $plan['pinned_wal_action'],
    'releasedWalAction' => $plan['released_wal_action'],
    'sourceTransitions' => $plan['source_transitions'],
    'readerReleaseUnblockedTruncate' => $plan['reader_release_unblocked_truncate'],
    'dependencies' => $plan['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['status'] === 'wal-checkpoint-reader-truncate-savepoint-current-source-next128');
    assert($summary['pinnedCheckpointBusy'] === true);
    assert($summary['pinnedWalAction'] === 'preserve_wal');
    assert($summary['releasedWalAction'] === 'truncate_wal');
    assert($summary['readerReleaseUnblockedTruncate'] === true);
    echo "application-wal-checkpoint-reader-truncate-savepoint-current-source-next128 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
