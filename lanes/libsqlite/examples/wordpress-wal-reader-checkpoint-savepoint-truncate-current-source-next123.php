<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalReaderCheckpointSavepointTruncateCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp schema base')
    . $page('wp options base')
    . $page('wp autoload base')
    . $page('wp plugin base')
    . $page('wp transient base');

$salt1 = 0x12312301;
$salt2 = 0x12312302;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 123, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
$append = static function (int $pageNumber, int $commitPageCount, string $label) use (&$walBytes, &$seed, $salt1, $salt2, $page): void {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};

$append(1, 0, 'wp schema retained draft');
$append(2, 5, 'wp options retained commit');
$append(3, 0, 'wp autoload stale draft');
$append(4, 0, 'wp plugin stale draft');
$append(4, 5, 'wp plugin stale commit');
$append(5, 5, 'wp transient stale commit');
$append(2, 5, 'wp options stale tail');

$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wp-import-next123');
$savepoints->recordWalFrameWrite(1, 1);
$savepoints->recordWalFrameWrite(2, 2, true);
$savepoints->savepoint('plugin-settings-next123');
$savepoints->recordWalFrameWrite(3, 3);
$savepoints->recordWalFrameWrite(4, 4);
$savepoints->recordWalFrameWrite(5, 4, true);
$savepoints->recordWalFrameWrite(6, 5, true);
$savepoints->recordWalFrameWrite(7, 2, true);

$plan = SQLiteWalReaderCheckpointSavepointTruncateCurrentSourceNextPlan::readerCheckpointSavepointTruncateWithStaleReaderPlan(
    $savepoints,
    'plugin-settings-next123',
    $wal,
    $walBytes,
    $walBytes,
    $databaseBytes,
    [1, 2, 3, 4, 5],
    7
);

$report = [
    'scenario' => 'wordpress-wal-reader-checkpoint-savepoint-truncate-current-source-next123',
    'wordpressUse' => 'During a copied wp_options import, a stale reader keeps a pre-rollback WAL source while savepoint rollback keeps only the retained WAL prefix; once the reader releases, truncate checkpoint makes next readers use the checkpointed database and an empty WAL sidecar without requiring ext/sqlite.',
    'status' => $plan['status'],
    'pinnedReason' => $plan['pinned_checkpoint_reason'],
    'releasedReason' => $plan['released_checkpoint_reason'],
    'releasedWalAction' => $plan['released_wal_action'],
    'retainedFrames' => $plan['retained_frame_count'],
    'discardedFrames' => $plan['discarded_frame_count'],
    'stalePages' => $plan['stale_reader_tail_pages'],
    'currentSources' => $plan['current_sources'],
    'releasedSources' => $plan['released_next_sources'],
    'releasedWalBytes' => $plan['released_wal_bytes_length'],
    'dependencies' => $plan['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    assert($report['status'] === 'reader-checkpoint-savepoint-truncate-current-source-next123');
    assert($report['pinnedReason'] === 'reader_blocks_wal_reset');
    assert($report['releasedReason'] === 'truncate_checkpoint_can_reset_and_truncate_wal');
    assert($report['releasedWalAction'] === 'truncate_wal');
    assert($report['retainedFrames'] === 2);
    assert($report['discardedFrames'] === 5);
    assert($report['stalePages'] === [2, 3, 4, 5]);
    assert($report['releasedWalBytes'] === 0);
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
