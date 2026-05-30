<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$salt1 = 0x13913901;
$salt2 = 0x13913902;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('next139 base schema')
    . $page('next139 base active_plugins')
    . $page('next139 base plugin settings')
    . $page('next139 base transient cache')
    . $page('next139 base option index');

$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 139, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
$append = static function (int $pageNumber, int $commitPageCount, string $label) use (&$walBytes, &$seed, $salt1, $salt2, $page): void {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};

$append(1, 0, 'next139 retained schema draft');
$append(2, 5, 'next139 retained active_plugins commit');
$append(3, 0, 'next139 rolled plugin settings draft');
$append(3, 5, 'next139 rolled plugin settings commit');
$append(4, 0, 'next139 rolled transient draft');
$append(4, 5, 'next139 rolled transient commit');
$append(5, 5, 'next139 rolled option index commit');

$makeShm = static function (array $readMarks, array $readLocks, int $backfill, int $attempted, int $mxFrame = 7) use ($pageSize, $salt1, $salt2): string {
    $pageSizeField = (1 << 24) | $pageSize;
    $header = pack('V*', 3007000, $backfill, 139, $pageSizeField, $mxFrame, 5, 1, 2, $salt1, $salt2, 5, 6);
    $marks = array_map(static fn ($value): int => $value === null ? 0xffffffff : $value, array_pad(array_values($readMarks), SQLiteShmIndex::READER_COUNT, null));
    $locks = array_pad(array_map(static fn (bool $held): string => $held ? "\x01" : "\x00", array_values($readLocks)), 8, "\x00");
    $checkpoint = pack('V*', $backfill, $marks[0], $marks[1], $marks[2], $marks[3], $marks[4])
        . implode('', array_slice($locks, 0, 8))
        . pack('V*', $attempted, 0);

    return $header . $header . $checkpoint;
};

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp-import-next139');
$stack->recordWalFrameWrite(1, 1);
$stack->recordWalFrameWrite(2, 2, true);
$stack->savepoint('plugin-settings-next139');
$stack->recordWalFrameWrite(3, 3);
$stack->recordWalFrameWrite(4, 3, true);
$stack->savepoint('transient-cache-next139');
$stack->recordWalFrameWrite(5, 4);
$stack->recordWalFrameWrite(6, 4, true);
$stack->recordWalFrameWrite(7, 5, true);

$plan = SQLiteWalSavepointCheckpointPlan::readerCheckpointSavepointCurrentSourceNext(
    $stack,
    'plugin-settings-next139',
    SQLiteWal::parse($walBytes, $pageSize, true),
    $walBytes,
    $databaseBytes,
    SQLiteShmIndex::parse($makeShm([0, 6, null, null, null], [false, true, false, false, false], 1, 4)),
    SQLiteShmIndex::parse($makeShm([0, null, null, null, null], [false, false, false, false, false], 7, 7)),
    [1, 2, 3, 4, 5],
    'restart'
);

echo json_encode([
    'database' => '/srv/www/wp-content/database/wp.sqlite',
    'wal' => '/srv/www/wp-content/database/wp.sqlite-wal',
    'savepoint' => $plan['savepoint'],
    'status' => $plan['status'],
    'activeReaderFrame' => $plan['active_reader_end_frame'],
    'writerCurrentFrame' => $plan['writer_current_reader_end_frame'],
    'retainedFrames' => $plan['retained_frame_count'],
    'discardedFrames' => $plan['discarded_frame_count'],
    'activeReaderSources' => $plan['active_reader_sources'],
    'writerCurrentSources' => $plan['writer_current_sources'],
    'releasedNextSources' => $plan['released_next_sources'],
    'activeReaderBlocksCheckpointReset' => $plan['active_reader_blocks_checkpoint_reset'],
    'readerReleaseUnblocksCheckpoint' => $plan['reader_release_unblocks_checkpoint'],
    'releasedNextUsesCheckpointDatabase' => $plan['released_next_uses_checkpoint_database'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT) . "\n";
